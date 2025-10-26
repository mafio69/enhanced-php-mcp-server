<?php

namespace App\Services; // Poprawiona przestrzeń nazw

use Psr\Log\LoggerInterface;

class MonitoringService {
    private LoggerInterface $logger;
    private array $metrics = [];
    private float $startTime;

    public function __construct(LoggerInterface $logger) {
        $this->logger = $logger;
        $this->startTime = microtime(true);
    }

    public function incrementCounter(string $name, array $tags = []): void {
        $key = $this->buildKey($name, $tags);
        $this->metrics[$key] = ($this->metrics[$key] ?? 0) + 1;

        $this->logger->debug("Counter incremented", [
            'metric' => $name,
            'value' => $this->metrics[$key],
            'tags' => $tags
        ]);
    }

    public function recordTiming(string $name, float $duration, array $tags = []): void {
        $key = $this->buildKey($name . '_timing', $tags);

        if (!isset($this->metrics[$key])) {
            $this->metrics[$key] = [];
        }

        $this->metrics[$key][] = $duration;

        $this->logger->debug("Timing recorded", [
            'metric' => $name,
            'duration' => $duration,
            'tags' => $tags
        ]);
    }

    public function recordGauge(string $name, float $value, array $tags = []): void {
        $key = $this->buildKey($name, $tags);
        $this->metrics[$key] = $value;

        $this->logger->debug("Gauge recorded", [
            'metric' => $name,
            'value' => $value,
            'tags' => $tags
        ]);
    }

    public function recordToolExecution(string $toolName, float $duration, bool $success = true): void {
        $this->incrementCounter('tool_executions', ['tool' => $toolName, 'success' => $success ? 'true' : 'false']);
        $this->recordTiming('tool_execution_time', $duration, ['tool' => $toolName]);

        if (!$success) {
            $this->incrementCounter('tool_failures', ['tool' => $toolName]);
        }

        $this->logger->info("Tool executed", [
            'tool' => $toolName,
            'duration' => $duration,
            'success' => $success
        ]);
    }

    public function recordHttpRequest(string $method, string $endpoint, int $statusCode, float $duration): void {
        $this->incrementCounter('http_requests', [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => (string)$statusCode
        ]);

        $this->recordTiming('http_request_duration', $duration, [
            'method' => $method,
            'endpoint' => $endpoint
        ]);

        $this->logger->info("HTTP request recorded", [
            'method' => $method,
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'duration' => $duration
        ]);
    }

    public function getSystemMetrics(): array {
        $memoryUsage = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        $uptime = microtime(true) - $this->startTime;

        $this->recordGauge('memory_usage_bytes', $memoryUsage);
        $this->recordGauge('memory_peak_bytes', $memoryPeak);
        $this->recordGauge('uptime_seconds', $uptime);

        return [
            'memory_usage' => $this->formatBytes($memoryUsage),
            'memory_peak' => $this->formatBytes($memoryPeak),
            'uptime' => $this->formatDuration($uptime),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    public function getMetrics(): array {
        $result = [];

        foreach ($this->metrics as $key => $value) {
            if (is_array($value)) {
                // Timing metrics
                $result[$key] = [
                    'count' => count($value),
                    'sum' => array_sum($value),
                    'avg' => array_sum($value) / count($value),
                    'min' => min($value),
                    'max' => max($value)
                ];
            } else {
                // Counter and gauge metrics
                $result[$key] = $value;
            }
        }

        return array_merge($result, $this->getSystemMetrics());
    }

    public function resetMetrics(): void {
        $this->metrics = [];
        $this->logger->info("Metrics reset");
    }

    private function buildKey(string $name, array $tags): string {
        if (empty($tags)) {
            return $name;
        }

        ksort($tags);
        $tagString = implode(',', array_map(
            fn($k, $v) => "$k=$v",
            array_keys($tags),
            $tags
        ));

        return $name . '[' . $tagString . ']';
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    private function formatDuration(float $seconds): string {
        if ($seconds < 60) {
            return round($seconds, 2) . 's';
        } elseif ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            return $minutes . 'm ' . round($remainingSeconds, 2) . 's';
        } else {
            $hours = floor($seconds / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            return $hours . 'h ' . $remainingMinutes . 'm';
        }
    }
}
