<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class GetTimeTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $format = $arguments['format'] ?? 'Y-m-d H:i:s';
        $timezone = $arguments['timezone'] ?? 'UTC';

        try {
            $date = new \DateTime('now', new \DateTimeZone($timezone));
            return "Aktualny czas: " . $date->format($format) . " ({$timezone})";
        } catch (\Exception $e) {
            return "Current time: " . date($format);
        }
    }

    public function getName(): string
    {
        return 'get_time';
    }

    public function getDescription(): string
    {
        return 'Zwraca aktualny czas';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'format' => [
                    'type' => 'string',
                    'description' => 'Format daty PHP (opcjonalne)'
                ],
                'timezone' => [
                    'type' => 'string',
                    'description' => 'Strefa czasowa (opcjonalne)'
                ]
            ],
            'required' => []
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }
}