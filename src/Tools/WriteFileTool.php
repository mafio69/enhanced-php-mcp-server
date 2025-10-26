<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class WriteFileTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $path = $arguments['path'] ?? '';
        $content = $arguments['content'] ?? '';

        if (empty($path)) throw new \Exception("Ścieżka do pliku jest wymagana");

        $fullPath = $this->validatePath($path);
        $bytes = file_put_contents($fullPath, $content);

        return "Plik zapisany: {$path}\nZapisano bajtów: {$bytes}";
    }

    public function getName(): string { return 'write_file'; }
    public function getDescription(): string { return 'Zapisuje zawartość do pliku'; }
    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Ścieżka do pliku'],
                'content' => ['type' => 'string', 'description' => 'Zawartość do zapisania']
            ],
            'required' => ['path', 'content']
        ];
    }
    public function isEnabled(): bool { return true; }

    private function validatePath(string $path): string {
        $path = str_replace(['../', '..\\'], '', $path);
        $fullPath = __DIR__ . '/../../' . ltrim($path, './');
        $dir = dirname($fullPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $fullPath;
    }
}