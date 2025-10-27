<?php

namespace App\Services;

use App\Context\UserContext;
use App\Logger;
use Psr\Log\LogLevel;
use Stringable;

class UserAwareLogger extends Logger
{
    private ?UserContext $userContext = null;
    private array $userLogBuffer = [];
    private bool $bufferingEnabled = false;
    private int $maxBufferSize = 100;

    public function setUserContext(?UserContext $userContext): void
    {
        $this->userContext = $userContext;

        // Flush buffer when user context changes
        if ($this->bufferingEnabled && !empty($this->userLogBuffer)) {
            $this->flushUserLogBuffer();
        }
    }

    public function getUserContext(): ?UserContext
    {
        return $this->userContext;
    }

    public function enableBuffering(bool $enabled = true, int $maxSize = 100): void
    {
        $this->bufferingEnabled = $enabled;
        $this->maxBufferSize = $maxSize;

        if (!$enabled && !empty($this->userLogBuffer)) {
            $this->flushUserLogBuffer();
        }
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log(string $level, string|Stringable $message, array $context = []): void
    {
        $enrichedContext = $this->enrichContextWithUserInfo($context);

        if ($this->bufferingEnabled && $this->userContext && $this->userContext->isAuthenticated()) {
            $this->addToUserLogBuffer($level, $message, $enrichedContext);
        } else {
            parent::log($level, $message, $enrichedContext);
        }
    }

    // User-specific logging methods
    public function logUserActivity(string $action, array $details = [], string $level = LogLevel::INFO): void
    {
        $context = array_merge([
            'action' => $action,
            'category' => 'user_activity',
            'timestamp' => time()
        ], $details);

        $this->log($level, "User activity: {$action}", $context);
    }

    public function logSecretOperation(string $operation, string $secretKey, array $details = [], string $level = LogLevel::INFO): void
    {
        $context = array_merge([
            'secret_operation' => $operation,
            'secret_key' => $secretKey,
            'category' => 'secret_management',
            'timestamp' => time()
        ], $details);

        $this->log($level, "Secret operation: {$operation} on '{$secretKey}'", $context);
    }

    public function logToolExecution(string $toolName, array $arguments = [], float $executionTime = 0, bool $success = true): void
    {
        $context = [
            'tool_name' => $toolName,
            'tool_arguments' => $this->sanitizeArguments($arguments),
            'execution_time' => $executionTime,
            'success' => $success,
            'category' => 'tool_execution',
            'timestamp' => time()
        ];

        $level = $success ? LogLevel::INFO : LogLevel::ERROR;
        $message = "Tool execution: {$toolName} " . ($success ? 'succeeded' : 'failed');

        $this->log($level, $message, $context);
    }

    public function logAuthenticationEvent(string $event, array $details = [], string $level = LogLevel::INFO): void
    {
        $context = array_merge([
            'auth_event' => $event,
            'category' => 'authentication',
            'timestamp' => time()
        ], $details);

        $this->log($level, "Authentication: {$event}", $context);
    }

    public function logSecurityEvent(string $event, array $details = [], string $level = LogLevel::WARNING): void
    {
        $context = array_merge([
            'security_event' => $event,
            'category' => 'security',
            'timestamp' => time()
        ], $details);

        $this->log($level, "Security event: {$event}", $context);
    }

    // User-specific log retrieval
    public function getUserLogs(int $userId, array $filters = []): array
    {
        $logFile = $this->getLogPath();

        if (!file_exists($logFile)) {
            return [];
        }

        $logs = [];
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $logEntry = $this->parseLogLine($line);

            if ($logEntry && isset($logEntry['context']['user_id']) && $logEntry['context']['user_id'] == $userId) {
                if ($this->matchesFilters($logEntry, $filters)) {
                    $logs[] = $logEntry;
                }
            }
        }

        // Apply additional filters
        if (isset($filters['limit'])) {
            $logs = array_slice($logs, 0, $filters['limit']);
        }

        if (isset($filters['level'])) {
            $logs = array_filter($logs, function($log) use ($filters) {
                return $log['level'] === $filters['level'];
            });
        }

        return array_values($logs);
    }

    public function getUserActivitySummary(int $userId, \DateTime $startDate = null, \DateTime $endDate = null): array
    {
        $startDate = $startDate ?: (new \DateTime())->sub(new \DateInterval('P7D')); // Last 7 days
        $endDate = $endDate ?: new \DateTime();

        $logs = $this->getUserLogs($userId, [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $summary = [
            'total_logs' => count($logs),
            'date_range' => [
                'start' => $startDate->format('Y-m-d H:i:s'),
                'end' => $endDate->format('Y-m-d H:i:s')
            ],
            'categories' => [],
            'levels' => [],
            'actions' => [],
            'tools_used' => [],
            'secret_operations' => [],
            'daily_activity' => []
        ];

        foreach ($logs as $log) {
            $category = $log['context']['category'] ?? 'general';
            $level = $log['level'];
            $date = date('Y-m-d', strtotime($log['timestamp']));

            // Category stats
            $summary['categories'][$category] = ($summary['categories'][$category] ?? 0) + 1;

            // Level stats
            $summary['levels'][$level] = ($summary['levels'][$level] ?? 0) + 1;

            // Daily activity
            $summary['daily_activity'][$date] = ($summary['daily_activity'][$date] ?? 0) + 1;

            // Specific category details
            if ($category === 'user_activity' && isset($log['context']['action'])) {
                $action = $log['context']['action'];
                $summary['actions'][$action] = ($summary['actions'][$action] ?? 0) + 1;
            }

            if ($category === 'tool_execution' && isset($log['context']['tool_name'])) {
                $tool = $log['context']['tool_name'];
                $summary['tools_used'][$tool] = ($summary['tools_used'][$tool] ?? 0) + 1;
            }

            if ($category === 'secret_management' && isset($log['context']['secret_operation'])) {
                $operation = $log['context']['secret_operation'];
                $summary['secret_operations'][$operation] = ($summary['secret_operations'][$operation] ?? 0) + 1;
            }
        }

        return $summary;
    }

    // Private helper methods
    private function enrichContextWithUserInfo(array $context): array
    {
        if (!$this->userContext) {
            return $context;
        }

        $userContext = [
            'session_id' => $this->userContext->getSessionId(),
            'ip_address' => $this->userContext->getIpAddress(),
            'user_agent' => $this->userContext->getUserAgent(),
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if ($this->userContext->isAuthenticated()) {
            $user = $this->userContext->getUser();
            if ($user) {
                $userContext['user_id'] = $user->getId();
                $userContext['user_email'] = $user->getEmail();
                $userContext['user_role'] = $user->getRole();
                $userContext['user_name'] = $user->getName();
                $userContext['is_impersonated'] = $this->userContext->isImpersonated();

                if ($this->userContext->isImpersonated()) {
                    $userContext['original_user_id'] = $this->userContext->getOriginalUserId();
                }
            }
        } else {
            $userContext['user_id'] = null;
            $userContext['user_email'] = 'guest';
            $userContext['user_role'] = 'guest';
            $userContext['user_name'] = 'Guest User';
        }

        return array_merge($context, $userContext);
    }

    private function sanitizeArguments(array $arguments): array
    {
        $sensitive = ['password', 'token', 'key', 'secret', 'api_key', 'auth'];

        foreach ($arguments as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $sensitive)) {
                $arguments[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $arguments[$key] = $this->sanitizeArguments($value);
            }
        }

        return $arguments;
    }

    private function addToUserLogBuffer(string $level, string $message, array $context): void
    {
        $this->userLogBuffer[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true)
        ];

        if (count($this->userLogBuffer) >= $this->maxBufferSize) {
            $this->flushUserLogBuffer();
        }
    }

    private function flushUserLogBuffer(): void
    {
        if (empty($this->userLogBuffer)) {
            return;
        }

        foreach ($this->userLogBuffer as $logEntry) {
            parent::log($logEntry['level'], $logEntry['message'], $logEntry['context']);
        }

        $this->userLogBuffer = [];
    }

    private function parseLogLine(string $line): ?array
    {
        // Expected format: [2025-01-01 12:00:00] LEVEL: MESSAGE {"context":"data"}
        if (!preg_match('/^\[([^\]]+)\] (\w+): (.+?) (\{.+\})$/', $line, $matches)) {
            return null;
        }

        $timestamp = $matches[1];
        $level = strtolower($matches[2]);
        $message = $matches[3];
        $contextJson = $matches[4];

        $context = json_decode($contextJson, true);
        if ($context === null) {
            $context = [];
        }

        return [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context
        ];
    }

    private function matchesFilters(array $logEntry, array $filters): bool
    {
        // Date range filter
        if (isset($filters['start_date'])) {
            $logDate = new \DateTime($logEntry['timestamp']);
            if ($logDate < $filters['start_date']) {
                return false;
            }
        }

        if (isset($filters['end_date'])) {
            $logDate = new \DateTime($logEntry['timestamp']);
            if ($logDate > $filters['end_date']) {
                return false;
            }
        }

        // Category filter
        if (isset($filters['category'])) {
            $category = $logEntry['context']['category'] ?? 'general';
            if ($category !== $filters['category']) {
                return false;
            }
        }

        // Action filter
        if (isset($filters['action'])) {
            $action = $logEntry['context']['action'] ?? null;
            if ($action !== $filters['action']) {
                return false;
            }
        }

        // Search filter
        if (isset($filters['search'])) {
            $search = strtolower($filters['search']);
            $searchable = strtolower($logEntry['message'] . json_encode($logEntry['context']));
            if (strpos($searchable, $search) === false) {
                return false;
            }
        }

        return true;
    }

    // Log management
    public function archiveUserLogs(int $userId, string $archivePath): bool
    {
        $logs = $this->getUserLogs($userId);

        if (empty($logs)) {
            return false;
        }

        $archiveData = [
            'user_id' => $userId,
            'export_date' => date('Y-m-d H:i:s'),
            'total_logs' => count($logs),
            'logs' => $logs
        ];

        $archiveJson = json_encode($archiveData, JSON_PRETTY_PRINT);
        $result = file_put_contents($archivePath, $archiveJson);

        return $result !== false;
    }

    public function cleanupOldUserLogs(int $userId, int $daysToKeep = 90): int
    {
        $cutoffDate = (new \DateTime())->sub(new \DateInterval("P{$daysToKeep}D"));
        $allLogs = $this->getUserLogs($userId);

        $keptLogs = [];
        $deletedCount = 0;

        foreach ($allLogs as $log) {
            $logDate = new \DateTime($log['timestamp']);
            if ($logDate >= $cutoffDate) {
                $keptLogs[] = $log;
            } else {
                $deletedCount++;
            }
        }

        // In a real implementation, we would rewrite the log file
        // For now, we just return the count of logs that would be deleted

        return $deletedCount;
    }
}