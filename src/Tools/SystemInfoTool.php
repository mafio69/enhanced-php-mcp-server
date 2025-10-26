<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class SystemInfoTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $info = "=== INFORMACJE O SYSTEMIE ===\n";
        $info .= "Operating System: " . PHP_OS . "\n";
        $info .= "Wersja PHP: " . PHP_VERSION . "\n";
        $info .= "Architecture: " . (PHP_INT_SIZE * 8) . "-bit\n";
        $info .= "Hostname: " . gethostname() . "\n";
        $info .= "Memory Usage: " . $this->formatBytes(memory_get_usage(true)) . "\n";
        $info .= "Peak Memory: " . $this->formatBytes(memory_get_peak_usage(true)) . "\n";
        $info .= "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
        $info .= "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";

        return $info;
    }

    public function getName(): string
    {
        return 'system_info';
    }

    public function getDescription(): string
    {
        return 'Zwraca informacje o systemie';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
            'required' => []
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}