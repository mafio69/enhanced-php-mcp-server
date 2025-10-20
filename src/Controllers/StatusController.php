<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ServerInfo;
use App\DTO\ServerStatusResponse;
use App\DTO\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class StatusController extends BaseController
{
    public function getServerStatus(Request $request, Response $response): Response
    {
        try {
            $serverInfo = new ServerInfo(
                $this->config->getName(),
                $this->config->getVersion(),
                $this->config->getDescription(),
                'running',
                [
                    'php_version' => PHP_VERSION,
                    'memory_usage' => memory_get_usage(true),
                    'peak_memory' => memory_get_peak_usage(true),
                    'uptime' => $this->getServerUptime(),
                    'debug_mode' => $this->config->isDebugMode()
                ]
            );

            $metrics = $this->getSystemMetrics();
            $tools = $this->getToolsSummary();

            $statusResponse = ServerStatusResponse::running($serverInfo, $metrics, $tools);

            return $this->jsonResponse($response, $statusResponse->toArray());

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Failed to get server status: ' . $e->getMessage()
            ));
        }
    }

    public function getMetrics(Request $request, Response $response): Response
    {
        try {
            $metrics = $this->getDetailedMetrics();

            return $this->jsonResponse($response, [
                'success' => true,
                'metrics' => $metrics,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Failed to get metrics: ' . $e->getMessage()
            ));
        }
    }

    public function getHealth(Request $request, Response $response): Response
    {
        try {
            $checks = [
                'database' => $this->checkDatabase(),
                'filesystem' => $this->checkFilesystem(),
                'memory' => $this->checkMemory(),
                'logging' => $this->checkLogging(),
                'tools' => $this->checkTools()
            ];

            $healthy = array_filter($checks, fn($check) => $check['status'] === 'ok');
            $unhealthy = array_filter($checks, fn($check) => $check['status'] !== 'ok');

            $isHealthy = empty($unhealthy);
            $statusCode = $isHealthy ? 200 : 503;

            return $this->jsonResponse($response, [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'checks' => $checks,
                'summary' => [
                    'total' => count($checks),
                    'passed' => count($healthy),
                    'failed' => count($unhealthy)
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], $statusCode);

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Health check failed: ' . $e->getMessage()
            ));
        }
    }

    private function getServerUptime(): string
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return number_format($load[0], 2) . ' (1min avg)';
        }

        return 'N/A';
    }

    private function getSystemMetrics(): array
    {
        return [
            'memory' => [
                'usage' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
                'usage_percent' => $this->getMemoryUsagePercent()
            ],
            'server' => [
                'php_version' => PHP_VERSION,
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'request_time' => $_SERVER['REQUEST_TIME'] ?? time(),
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '/api/status'
            ],
            'tools' => [
                'enabled_count' => count($this->config->getEnabledTools()),
                'restricted_count' => count($this->config->getRestrictedTools()),
                'total_count' => 10
            ]
        ];
    }

    private function getToolsSummary(): array
    {
        $enabledTools = $this->config->getEnabledTools();
        $restrictedTools = $this->config->getRestrictedTools();

        return [
            'enabled' => $enabledTools,
            'restricted' => $restrictedTools,
            'available_count' => count($enabledTools),
            'restricted_count' => count($restrictedTools)
        ];
    }

    private function getDetailedMetrics(): array
    {
        return [
            'performance' => [
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'memory_limit' => $this->parseMemoryLimit(ini_get('memory_limit')),
                'execution_time' => microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? time()),
                'included_files' => count(get_included_files()),
                'declared_classes' => count(get_declared_classes()),
                'declared_interfaces' => count(get_declared_interfaces()),
                'declared_traits' => count(get_declared_traits())
            ],
            'php' => [
                'version' => PHP_VERSION,
                'sapi' => PHP_SAPI,
                'max_execution_time' => ini_get('max_execution_time'),
                'post_max_size' => $this->parseMemoryLimit(ini_get('post_max_size')),
                'upload_max_filesize' => $this->parseMemoryLimit(ini_get('upload_max_filesize')),
                'display_errors' => ini_get('display_errors') === '1',
                'error_reporting' => $this->getErrorReportingLevel()
            ],
            'server' => [
                'os' => PHP_OS,
                'uname' => php_uname(),
                'hostname' => gethostname(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
                'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown'
            ],
            'filesystem' => [
                'disk_free_space' => disk_free_space('.'),
                'disk_total_space' => disk_total_space('.'),
                'disk_usage_percent' => $this->getDiskUsagePercent()
            ]
        ];
    }

    private function checkDatabase(): array
    {
        // For now, we don't have database, so return not applicable
        return [
            'status' => 'na',
            'message' => 'Database not configured'
        ];
    }

    private function checkFilesystem(): array
    {
        $logFile = $this->config->getLogFile();
        $logDir = dirname($logFile);

        $writable = is_writable($logDir);
        $freeSpace = disk_free_space('.');

        return [
            'status' => $writable ? 'ok' : 'error',
            'message' => $writable ? 'Filesystem is writable' : 'Filesystem is not writable',
            'details' => [
                'log_directory' => $logDir,
                'writable' => $writable,
                'free_space' => $freeSpace
            ]
        ];
    }

    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $usagePercent = ($usage / $limit) * 100;

        $status = $usagePercent < 80 ? 'ok' : ($usagePercent < 95 ? 'warning' : 'error');

        return [
            'status' => $status,
            'message' => "Memory usage: {$usagePercent}%",
            'details' => [
                'usage' => $usage,
                'limit' => $limit,
                'usage_percent' => round($usagePercent, 2)
            ]
        ];
    }

    private function checkLogging(): array
    {
        $logFile = $this->config->getLogFile();
        $logDir = dirname($logFile);

        $enabled = $this->config->isLoggingEnabled();
        $writable = is_writable($logDir);
        $exists = file_exists($logFile);

        $status = ($enabled && $writable) ? 'ok' : 'error';

        return [
            'status' => $status,
            'message' => $enabled ? 'Logging is enabled' : 'Logging is disabled',
            'details' => [
                'enabled' => $enabled,
                'log_file' => $logFile,
                'writable' => $writable,
                'exists' => $exists
            ]
        ];
    }

    private function checkTools(): array
    {
        $enabledTools = $this->config->getEnabledTools();
        $hasTools = !empty($enabledTools);

        return [
            'status' => $hasTools ? 'ok' : 'error',
            'message' => $hasTools ? 'Tools are configured' : 'No tools configured',
            'details' => [
                'enabled_count' => count($enabledTools),
                'tools' => $enabledTools
            ]
        ];
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $value = (int) $limit;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    private function getMemoryUsagePercent(): float
    {
        $usage = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));

        return $limit > 0 ? round(($usage / $limit) * 100, 2) : 0;
    }

    private function getDiskUsagePercent(): float
    {
        $free = disk_free_space('.');
        $total = disk_total_space('.');

        return $total > 0 ? round((($total - $free) / $total) * 100, 2) : 0;
    }

    private function getErrorReportingLevel(): string
    {
        $level = error_reporting();

        $levels = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
        ];

        $activeLevels = [];
        foreach ($levels as $value => $name) {
            if ($level & $value) {
                $activeLevels[] = $name;
            }
        }

        return implode(' | ', $activeLevels);
    }
}