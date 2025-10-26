<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class HelloTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $name = $arguments['name'] ?? 'World';
        return "Hello, {$name}! Nice to meet you.";
    }

    public function getName(): string
    {
        return 'hello';
    }

    public function getDescription(): string
    {
        return 'Zwraca powitanie';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Imię do powitania'
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