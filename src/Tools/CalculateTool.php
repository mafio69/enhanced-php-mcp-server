<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class CalculateTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $operation = $arguments['operation'] ?? null;
        $a = $arguments['a'] ?? null;
        $b = $arguments['b'] ?? null;

        if ($operation === null) {
            throw new \Exception("Operacja jest wymagana");
        }

        if ($a === null || $b === null) {
            throw new \Exception("Obie liczby są wymagane");
        }

        $a = floatval($a);
        $b = floatval($b);

        if (!is_numeric($a) || !is_numeric($b)) {
            throw new \Exception("Oba argumenty muszą być liczbami");
        }

        return match ($operation) {
            'add' => "Wynik: " . ($a + $b),
            'subtract' => "Wynik: " . ($a - $b),
            'multiply' => "Wynik: " . ($a * $b),
            'divide' => $b != 0 ? "Wynik: " . ($a / $b) : "Błąd: Dzielenie przez zero",
            default => throw new \Exception("Nieznana operacja: {$operation}")
        };
    }

    public function getName(): string
    {
        return 'calculate';
    }

    public function getDescription(): string
    {
        return 'Wykonuje proste obliczenia';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'description' => 'Operacja: add, subtract, multiply, divide'
                ],
                'a' => [
                    'type' => 'number',
                    'description' => 'Pierwsza liczba'
                ],
                'b' => [
                    'type' => 'number',
                    'description' => 'Druga liczba'
                ]
            ],
            'required' => ['operation', 'a', 'b']
        ];
    }

    public function isEnabled(): bool
    {
        return true;
    }
}