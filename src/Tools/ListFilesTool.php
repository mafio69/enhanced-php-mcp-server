<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class ListFilesTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $path = $arguments['path'] ?? '.';
        $fullPath = $this->validatePath($path);

        if (!is_dir($fullPath)) {
            throw new \Exception("Katalog nie znaleziony: {$path}");
        }

        $result = "Pliki w katalogu: {$path}\n";
        $result .= str_repeat("=", 50) . "\n";

        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
            $size = is_file($itemPath) ? ' (' . $this->formatBytes(filesize($itemPath)) . ')' : '';
            $modified = date('Y-m-d H:i:s', filemtime($itemPath));

            $result .= sprintf("%-10s %-30s %s %s\n", $type, $item, $modified, $size);
        }

        return $result;
    }

    public function getName(): string
    {
        return 'list_files';
    }

    public function getDescription(): string
    {
        return 'Wyświetla listę plików w katalogu';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => [
                    'type' => 'string',
                    'description' => 'Ścieżka do katalogu (opcjonalne)'
                ]
            ],
            'required' => []
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    private function validatePath(string $path): string
    {
        $path = str_replace(['../', '..\\'], '', $path);
        $fullPath = realpath(__DIR__ . '/../../' . $path);

        if ($fullPath === false) {
            $fullPath = realpath($path);
        }

        if ($fullPath === false) {
            throw new \Exception("Nieprawidłowa ścieżka: {$path}");
        }

        return $fullPath;
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