<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class LogsController extends BaseController
{
    public function getLogs(Request $request, Response $response): Response
    {
        try {
            $queryParams = $request->getQueryParams();
            $limit = (int) ($queryParams['limit'] ?? 50);
            $level = $queryParams['level'] ?? null;
            $search = $queryParams['search'] ?? null;

            $logs = $this->readLogFile($limit, $level, $search);

            return $this->jsonResponse($response, [
                'success' => true,
                'logs' => $logs,
                'count' => count($logs),
                'limit' => $limit,
                'filters' => [
                    'level' => $level,
                    'search' => $search
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Failed to retrieve logs: ' . $e->getMessage()
            ));
        }
    }

    public function clearLogs(Request $request, Response $response): Response
    {
        try {
            $logFile = $this->config->getLogFile();

            if (!file_exists($logFile)) {
                return $this->errorResponse($response, ErrorResponse::notFound(
                    'Log file does not exist'
                ));
            }

            // Backup current logs
            $backupFile = $logFile . '.backup.' . date('Y-m-d_H-i-s');
            if (!copy($logFile, $backupFile)) {
                return $this->errorResponse($response, ErrorResponse::internalError(
                    'Failed to backup log file'
                ));
            }

            // Clear log file
            if (file_put_contents($logFile, '') === false) {
                return $this->errorResponse($response, ErrorResponse::internalError(
                    'Failed to clear log file'
                ));
            }

            $this->logger->info('Logs cleared', [
                'backup_file' => $backupFile,
                'cleared_by' => 'API request'
            ]);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Logs cleared successfully',
                'backup_file' => basename($backupFile),
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Failed to clear logs: ' . $e->getMessage()
            ));
        }
    }

    public function downloadLogs(Request $request, Response $response): Response
    {
        try {
            $logFile = $this->config->getLogFile();

            if (!file_exists($logFile)) {
                return $this->errorResponse($response, ErrorResponse::notFound(
                    'Log file does not exist'
                ));
            }

            $logContent = file_get_contents($logFile);
            if ($logContent === false) {
                return $this->errorResponse($response, ErrorResponse::internalError(
                    'Failed to read log file'
                ));
            }

            $filename = 'server-logs-' . date('Y-m-d_H-i-s') . '.log';

            $response->getBody()->write($logContent);

            return $response
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withHeader('Content-Length', strlen($logContent));

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Failed to download logs: ' . $e->getMessage()
            ));
        }
    }

    public function getLogStats(Request $request, Response $response): Response
    {
        try {
            $logFile = $this->config->getLogFile();

            if (!file_exists($logFile)) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'exists' => false,
                    'message' => 'Log file does not exist',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            }

            $stats = $this->analyzeLogFile($logFile);

            return $this->jsonResponse($response, [
                'success' => true,
                'stats' => $stats,
                'timestamp' => date('Y-m-d H:i:s')
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($response, ErrorResponse::internalError(
                'Failed to get log stats: ' . $e->getMessage()
            ));
        }
    }

    private function readLogFile(int $limit, ?string $level, ?string $search): array
    {
        $logFile = $this->config->getLogFile();

        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        // Get last N lines
        $lines = array_slice($lines, -$limit);

        $logs = [];
        foreach ($lines as $line) {
            $parsedLog = $this->parseLogLine($line);

            // Apply filters
            if ($level && $parsedLog['level'] !== $level) {
                continue;
            }

            if ($search && !str_contains($line, $search)) {
                continue;
            }

            $logs[] = $parsedLog;
        }

        return array_reverse($logs); // Show newest first
    }

    private function parseLogLine(string $line): array
    {
        // Parse log line format: [2024-01-01 12:00:00] level.MESSAGE context
        $parsed = [
            'raw' => $line,
            'timestamp' => null,
            'level' => 'INFO',
            'message' => $line,
            'context' => []
        ];

        // Extract timestamp and level
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+(\w+)\.(.+)$/', $line, $matches)) {
            $parsed['timestamp'] = $matches[1];
            $parsed['level'] = $matches[2];
            $parsed['message'] = $matches[3];

            // Try to extract JSON context
            if (preg_match('/(.+?)\s+(\{.*\})$/', $matches[3], $contextMatches)) {
                $parsed['message'] = $contextMatches[1];
                $contextJson = $contextMatches[2];
                $context = json_decode($contextJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $parsed['context'] = $context;
                }
            }
        }

        return $parsed;
    }

    private function analyzeLogFile(string $logFile): array
    {
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        $stats = [
            'total_lines' => count($lines),
            'file_size' => filesize($logFile),
            'file_size_formatted' => $this->formatBytes(filesize($logFile)),
            'last_modified' => date('Y-m-d H:i:s', filemtime($logFile)),
            'levels' => [],
            'timeline' => [],
            'top_errors' => []
        ];

        $levelCounts = [];
        $timeline = [];
        $errors = [];

        foreach ($lines as $line) {
            $parsed = $this->parseLogLine($line);

            // Count levels
            $level = $parsed['level'];
            $levelCounts[$level] = ($levelCounts[$level] ?? 0) + 1;

            // Build timeline (last 24 hours grouped by hour)
            if ($parsed['timestamp']) {
                $hour = date('Y-m-d H:00', strtotime($parsed['timestamp']));
                $timeline[$hour] = ($timeline[$hour] ?? 0) + 1;
            }

            // Collect errors
            if (in_array($level, ['ERROR', 'CRITICAL', 'ALERT'])) {
                $errors[] = [
                    'timestamp' => $parsed['timestamp'],
                    'level' => $level,
                    'message' => $parsed['message']
                ];
            }
        }

        $stats['levels'] = $levelCounts;
        $stats['timeline'] = $timeline;
        $stats['top_errors'] = array_slice($errors, -10); // Last 10 errors

        return $stats;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }
}