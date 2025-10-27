<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class GetWeatherTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $city = $arguments['city'] ?? '';
        if (empty($city)) throw new \Exception("Nazwa miasta jest wymagana");

        // Get API key from environment variables
        $apiKey = $this->getWeatherApiKey();

        if (!$apiKey) {
            return $this->getFallbackWeather($city, "❌ WEATHER_API_KEY nie jest ustawiony");
        }

        // WeatherAPI.com endpoint
        $url = 'https://api.weatherapi.com/v1/current.json';
        $params = [
            'key' => $apiKey,
            'q' => $city,
            'aqi' => 'no',
            'lang' => 'pl'
        ];

        $fullUrl = $url . '?' . http_build_query($params);

        // Make API call
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: PHP-MCP-Server/2.1.0'
            ],
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return $this->getFallbackWeather($city, "❌ Błąd połączenia: " . $error);
        }

        if ($httpCode !== 200) {
            return $this->getFallbackWeather($city, "❌ Błąd API (HTTP {$httpCode})");
        }

        $data = json_decode($response, true);
        if (!$data || isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Nie można przetworzyć odpowiedzi JSON';
            return $this->getFallbackWeather($city, "❌ Błąd WeatherAPI: " . $errorMsg);
        }

        return $this->formatWeatherData($city, $data);
    }

    private function formatWeatherData(string $city, array $data): string
    {
        $location = $data['location'] ?? [];
        $current = $data['current'] ?? [];

        $result = "=== POGODA DLA MIASTA: " . strtoupper($city) . " ===\n";

        // Location info
        if (!empty($location['name'])) {
            $result .= "📍 Lokalizacja: {$location['name']}";
            if (!empty($location['region'])) $result .= ", {$location['region']}";
            if (!empty($location['country'])) $result .= ", {$location['country']}";
            $result .= "\n";
        }

        // Current weather
        if (!empty($current['temp_c'])) {
            $result .= "🌡️  Temperatura: " . round($current['temp_c']) . "°C\n";
        }

        if (!empty($current['condition']['text'])) {
            $result .= "☁️  Warunki: {$current['condition']['text']}\n";
        }

        if (!empty($current['humidity'])) {
            $result .= "💧 Wilgotność: {$current['humidity']}%\n";
        }

        if (!empty($current['wind_kph'])) {
            $windKph = $current['wind_kph'];
            $windKmh = round($windKph * 1.60934); // Convert to km/h
            $result .= "💨 Prędkość wiatru: {$windKmh} km/h\n";
        }

        if (!empty($current['pressure_mb'])) {
            $result .= "🔽 Ciśnienie: {$current['pressure_mb']} hPa\n";
        }

        if (!empty($current['vis_km'])) {
            $result .= "👁️  Widoczność: {$current['vis_km']} km\n";
        }

        if (!empty($current['uv'])) {
            $result .= "☀️ Indeks UV: {$current['uv']}\n";
        }

        $result .= "🕐 Ostatnia aktualizacja: " . date('Y-m-d H:i:s') . "\n";
        $result .= "\n📊 Dane z WeatherAPI.com • Dokładne dane pogodowe";

        return $result;
    }

    private function getFallbackWeather(string $city, string $error): string
    {
        // Fallback to simulation when API fails
        $conditions = ['Słonecznie', 'Pochmurnie', 'Deszczowo', 'Częściowe zachmurzenie', 'Bezchmurnie'];
        $condition = $conditions[array_rand($conditions)];
        $temperature = rand(-10, 35);
        $humidity = rand(30, 90);
        $windSpeed = rand(0, 25);

        return "=== POGODA DLA MIASTA: " . strtoupper($city) . " ===\n" .
               "{$error}\n\n" .
               "🔄 Używam danych symulowanych (fallback):\n" .
               "🌡️  Temperatura: {$temperature}°C\n" .
               "☁️  Warunki: {$condition}\n" .
               "💧 Wilgotność: {$humidity}%\n" .
               "💨 Prędkość wiatru: {$windSpeed} km/h\n" .
               "🕐 Ostatnia aktualizacja: " . date('Y-m-d H:i:s') . "\n\n" .
               "💡 Wskazówka: Ustaw WEATHER_API_KEY w .env dla prawdziwych danych pogodowych";
    }

    private function getWeatherApiKey(): ?string
    {
        // Try $_ENV first (from .env), then getenv()
        return $_ENV['WEATHER_API_KEY'] ?: getenv('WEATHER_API_KEY') ?? null;
    }

    public function getName(): string {
        return 'get_weather';
    }

    public function getDescription(): string {
        return 'Pobiera prawdziwe informacje o pogodzie dla miasta za pomocą WeatherAPI.com. Używaj angielskich nazw miast bez polskich znaków (Warsaw zamiast Warszawa, Krakow zamiast Kraków).';
    }

    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'city' => [
                    'type' => 'string',
                    'description' => 'Nazwa miasta po angielsku, bez polskich znaków (np. Warsaw, Krakow, Gdansk, London)'
                ]
            ],
            'required' => ['city']
        ];
    }

    public function isEnabled(): bool {
        return true;
    }
}