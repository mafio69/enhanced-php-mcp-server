<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class GetWeatherTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $city = $arguments['city'] ?? '';
        if (empty($city)) throw new \Exception("Nazwa miasta jest wymagana");

        $conditions = ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy', 'Clear'];
        $condition = $conditions[array_rand($conditions)];
        $temperature = rand(-10, 35);
        $humidity = rand(30, 90);
        $windSpeed = rand(0, 25);

        return "=== POGODA DLA MIASTA: " . strtoupper($city) . " ===\n" .
               "Weather condition: {$condition}\n" .
               "Temperature: {$temperature}°C\n" .
               "Humidity: {$humidity}%\n" .
               "Wind speed: {$windSpeed} km/h\n" .
               "Last updated: " . date('Y-m-d H:i:s') . "\n\n" .
               "Note: This is simulated weather data for demonstration purposes.";
    }

    public function getName(): string { return 'get_weather'; }
    public function getDescription(): string { return 'Pobiera informacje o pogodzie dla miasta'; }
    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'city' => ['type' => 'string', 'description' => 'Nazwa miasta']
            ],
            'required' => ['city']
        ];
    }
    public function isEnabled(): bool { return true; }
}