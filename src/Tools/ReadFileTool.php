<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class ReadFileTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $path = $arguments['path'] ?? '';
        if (empty($path)) {
            throw new \Exception("Ścieżka do pliku jest wymagana");
        }

        $fullPath = $this->validatePath($path);
        if (!file_exists($fullPath)) {
            throw new \Exception("Plik nie znaleziony: {$path}");
        }

        $content = file_get_contents($fullPath);
        return "Plik: {$path}\nRozmiar: " . strlen($content) . " bajtów\n\nZawartość:\n---\n" . $content;
    }

    public function getName(): string { return 'read_file'; }
    public function getDescription(): string { return 'Odczytuje zawartość pliku'; }
    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => ['path' => ['type' => 'string', 'description' => 'Ścieżka do pliku']],
            'required' => ['path']
        ];
    }
    public function isEnabled(): bool { return true; }

    private function validatePath(string $path): string {
        $path = str_replace(['../', '..\\'], '', $path);
        $fullPath = realpath(__DIR__ . '/../../' . $path);
        if ($fullPath === false) throw new \Exception("Nieprawidłowa ścieżka: {$path}");
        return $fullPath;
    }
}