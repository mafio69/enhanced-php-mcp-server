<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class JsonParseTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $json = $arguments['json'] ?? '';
        if (empty($json)) throw new \Exception("Ciąg JSON jest wymagany");

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $type = gettype($data);
        $count = is_array($data) ? count($data) : '';

        return "=== SPARSOWANY JSON ===\nRoot type: {$type}\n" .
               ($count ? "Element count: {$count}\n" : "") .
               "\nSformatowany JSON:\n---\n{$pretty}";
    }

    public function getName(): string { return 'json_parse'; }
    public function getDescription(): string { return 'Parsuje i formatuje JSON'; }
    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'json' => ['type' => 'string', 'description' => 'Tekst JSON do sparsowania']
            ],
            'required' => ['json']
        ];
    }
    public function isEnabled(): bool { return true; }
}