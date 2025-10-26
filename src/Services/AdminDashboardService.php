<?php

namespace App\Services;

use Exception;
use Psr\Log\LoggerInterface;

class AdminDashboardService
{
    private SecretService $secretService;
    private ToolsService $toolsService;
    private LoggerInterface $logger;
    private array $config;

    public function __construct(
        SecretService $secretService,
        ToolsService $toolsService,
        LoggerInterface $logger,
        array $config
    ) {
        $this->secretService = $secretService;
        $this->toolsService = $toolsService;
        $this->logger = $logger;
        $this->config = $config;
    }

    public function getDashboardData(): array
    {
        try {
            $data = [
                'server_info' => $this->getServerInfo(),
                'tools' => $this->toolsService->getAvailableTools(),
                'tools_by_category' => $this->toolsService->getToolsByCategory(),
                'secrets' => $this->secretService->listSecrets(),
                'system_info' => $this->getSystemInfo(),
                'recent_logs' => $this->getRecentLogs(),
                'stats' => $this->getStats()
            ];

            $this->logger->info('Dashboard data retrieved successfully');
            return $data;
        } catch (Exception $e) {
            $this->logger->error('Failed to get dashboard data', ['error' => $e->getMessage()]);
            throw new Exception('Failed to load dashboard: ' . $e->getMessage());
        }
    }

    public function getServerInfo(): array
    {
        try {
            return [
                'name' => $this->config['server']['name'] ?? 'MCP Server',
                'version' => $this->config['server']['version'] ?? '2.1.0',
                'description' => $this->config['server']['description'] ?? 'PHP MCP Server',
                'uptime' => $this->getUptime(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'php_version' => PHP_VERSION,
                'debug_mode' => $this->config['debug'] ?? false
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get server info', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getSystemInfo(): array
    {
        try {
            return [
                'php_version' => PHP_VERSION,
                'os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'current_memory_usage' => memory_get_usage(true),
                'peak_memory_usage' => memory_get_peak_usage(true),
                'disk_free_space' => function_exists('disk_free_space') ? disk_free_space('.') : 'N/A',
                'disk_total_space' => function_exists('disk_total_space') ? disk_total_space('.') : 'N/A',
                'loaded_extensions' => get_loaded_extensions(),
                'server_time' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get()
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get system info', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getRecentLogs(int $limit = 50): array
    {
        try {
            $logFile = $this->config['logging']['file'] ?? __DIR__ . '/../../logs/server.log';

            if (!file_exists($logFile)) {
                return [];
            }

            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                return [];
            }

            // Get last $limit lines
            $recentLines = array_slice($lines, -$limit);
            $logs = [];

            foreach ($recentLines as $line) {
                $parsed = $this->parseLogLine($line);
                if ($parsed) {
                    $logs[] = $parsed;
                }
            }

            return array_reverse($logs);
        } catch (Exception $e) {
            $this->logger->error('Failed to get recent logs', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getStats(): array
    {
        try {
            $secretsCount = count($this->secretService->listSecrets());
            $toolsCount = count($this->toolsService->getAvailableTools());

            // Get log stats
            $logFile = $this->config['logging']['file'] ?? __DIR__ . '/../../logs/server.log';
            $logStats = [
                'total_lines' => 0,
                'error_count' => 0,
                'warning_count' => 0,
                'info_count' => 0
            ];

            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines !== false) {
                    $logStats['total_lines'] = count($lines);

                    foreach ($lines as $line) {
                        if (strpos($line, '.ERROR') !== false) {
                            $logStats['error_count']++;
                        } elseif (strpos($line, '.WARNING') !== false) {
                            $logStats['warning_count']++;
                        } elseif (strpos($line, '.INFO') !== false) {
                            $logStats['info_count']++;
                        }
                    }
                }
            }

            return [
                'secrets_count' => $secretsCount,
                'tools_count' => $toolsCount,
                'logs' => $logStats,
                'uptime' => $this->getUptime(),
                'memory_usage_percent' => $this->getMemoryUsagePercent()
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get stats', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function performHealthCheck(): array
    {
        try {
            $checks = [
                'database' => $this->checkDatabase(),
                'filesystem' => $this->checkFilesystem(),
                'memory' => $this->checkMemory(),
                'tools' => $this->checkTools(),
                'secrets' => $this->checkSecrets(),
                'logging' => $this->checkLogging()
            ];

            $allHealthy = array_reduce($checks, function($carry, $check) {
                return $carry && $check['status'] === 'healthy';
            }, true);

            return [
                'overall_status' => $allHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'timestamp' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            $this->logger->error('Health check failed', ['error' => $e->getMessage()]);
            return [
                'overall_status' => 'unhealthy',
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    private function getUptime(): string
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                return sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2]);
            }

            // Fallback: use process start time if available
            if (isset($_SERVER['REQUEST_TIME'])) {
                $uptime = time() - $_SERVER['REQUEST_TIME'];
                return sprintf('%d days, %02d:%02d:%02d',
                    $uptime / 86400,
                    ($uptime % 86400) / 3600,
                    ($uptime % 3600) / 60,
                    $uptime % 60
                );
            }

            return 'N/A';
        } catch (Exception $e) {
            return 'N/A';
        }
    }

    private function getMemoryUsagePercent(): float
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            $currentUsage = memory_get_usage(true);

            if ($memoryLimit === '-1') {
                return 0.0; // Unlimited memory
            }

            $memoryLimitBytes = $this->parseMemoryLimit($memoryLimit);
            if ($memoryLimitBytes === 0) {
                return 0.0;
            }

            return round(($currentUsage / $memoryLimitBytes) * 100, 2);
        } catch (Exception $e) {
            return 0.0;
        }
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtolower(trim($limit));
        $multiplier = 1;

        if (str_ends_with($limit, 'g')) {
            $multiplier = 1024 * 1024 * 1024;
            $limit = substr($limit, 0, -1);
        } elseif (str_ends_with($limit, 'm')) {
            $multiplier = 1024 * 1024;
            $limit = substr($limit, 0, -1);
        } elseif (str_ends_with($limit, 'k')) {
            $multiplier = 1024;
            $limit = substr($limit, 0, -1);
        }

        return (int) $limit * $multiplier;
    }

    private function parseLogLine(string $line): ?array
    {
        try {
            // Parse log line format: [2024-01-01 12:00:00] channel.LEVEL: message
            if (preg_match('/^\[([^\]]+)\]\s+([^.]+)\.([^.]+):\s+(.+)$/', $line, $matches)) {
                return [
                    'timestamp' => $matches[1],
                    'channel' => $matches[2],
                    'level' => strtolower($matches[3]),
                    'message' => $matches[4],
                    'raw' => $line
                ];
            }

            return ['raw' => $line, 'level' => 'unknown'];
        } catch (Exception $e) {
            return ['raw' => $line, 'level' => 'unknown'];
        }
    }

    private function checkDatabase(): array
    {
        try {
            // Check if secrets file is accessible
            $secretsFile = __DIR__ . '/../../config/secrets.json';
            $accessible = file_exists($secretsFile) && is_readable($secretsFile);

            return [
                'status' => $accessible ? 'healthy' : 'unhealthy',
                'message' => $accessible ? 'Secrets file accessible' : 'Cannot access secrets file'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkFilesystem(): array
    {
        try {
            $logDir = dirname($this->config['logging']['file'] ?? __DIR__ . '/../../logs/server.log');
            $writable = is_writable($logDir);

            return [
                'status' => $writable ? 'healthy' : 'unhealthy',
                'message' => $writable ? 'Log directory writable' : 'Log directory not writable'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Filesystem check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkMemory(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
            $usagePercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

            $status = $usagePercent < 80 ? 'healthy' : 'unhealthy';
            $message = sprintf('Memory usage: %.2f%%', $usagePercent);

            return [
                'status' => $status,
                'message' => $message,
                'usage_bytes' => $memoryUsage,
                'limit_bytes' => $memoryLimit,
                'usage_percent' => round($usagePercent, 2)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Memory check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkTools(): array
    {
        try {
            $tools = $this->toolsService->getAvailableTools();
            $status = count($tools) > 0 ? 'healthy' : 'unhealthy';
            $message = sprintf('%d tools available', count($tools));

            return [
                'status' => $status,
                'message' => $message,
                'tools_count' => count($tools)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Tools check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkSecrets(): array
    {
        try {
            $secrets = $this->secretService->listSecrets();
            $status = 'healthy'; // Secrets system is healthy even if no secrets exist
            $message = sprintf('%d secrets stored', count($secrets));

            return [
                'status' => $status,
                'message' => $message,
                'secrets_count' => count($secrets)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Secrets check failed: ' . $e->getMessage()
            ];
        }
    }

    private function checkLogging(): array
    {
        try {
            $logFile = $this->config['logging']['file'] ?? __DIR__ . '/../../logs/server.log';
            $logDir = dirname($logFile);

            if (!is_dir($logDir)) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Log directory does not exist'
                ];
            }

            if (!is_writable($logDir)) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Log directory not writable'
                ];
            }

            return [
                'status' => 'healthy',
                'message' => 'Logging system operational',
                'log_file' => $logFile
            ];
        } catch (Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Logging check failed: ' . $e->getMessage()
            ];
        }
    }
}