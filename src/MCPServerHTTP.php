<?php

namespace App;

use Exception;
use JsonException;
use Psr\Log\LoggerInterface;

class MCPServerHTTP
{
    private array $tools = [];
    private LoggerInterface $logger;
    private Services\MonitoringService $monitoring;

    public function __construct(LoggerInterface $logger, Services\MonitoringService $monitoring)
    {
        $this->logger = $logger;
        $this->monitoring = $monitoring;

        $this->registerTools();
    }

    private function registerTools(): void
    {
        $this->registerTool('hello', 'Zwraca powitanie', [
            'name' => ['type' => 'string', 'description' => 'Imię do powitania'],
        ]);

        $this->registerTool('get_time', 'Zwraca aktualny czas', []);

        $this->registerTool('calculate', 'Wykonuje proste obliczenia', [
            'operation' => ['type' => 'string', 'description' => 'Operacja: add, subtract, multiply, divide'],
            'a' => ['type' => 'number', 'description' => 'Pierwsza liczba'],
            'b' => ['type' => 'number', 'description' => 'Druga liczba'],
        ]);

        $this->registerTool('list_files', 'Wyświetla listę plików w katalogu', [
            'path' => ['type' => 'string', 'description' => 'Ścieżka do katalogu (opcjonalne)'],
        ]);

        $this->registerTool('read_file', 'Odczytuje zawartość pliku', [
            'path' => ['type' => 'string', 'description' => 'Ścieżka do pliku'],
        ]);

        $this->registerTool('write_file', 'Zapisuje zawartość do pliku', [
            'path' => ['type' => 'string', 'description' => 'Ścieżka do pliku'],
            'content' => ['type' => 'string', 'description' => 'Zawartość do zapisania'],
        ]);

        $this->registerTool('system_info', 'Zwraca informacje o systemie', []);

        $this->registerTool('json_parse', 'Parsuje i formatuje JSON', [
            'json' => ['type' => 'string', 'description' => 'Tekst JSON do sparsowania'],
        ]);

        $this->registerTool('http_request', 'Wykonuje zapytanie HTTP do zewnętrznego API', [
            'url' => ['type' => 'string', 'description' => 'URL do wywołania'],
            'method' => ['type' => 'string', 'description' => 'Metoda HTTP (GET, POST, PUT, DELETE) - domyślnie GET'],
            'headers' => ['type' => 'string', 'description' => 'Nagłówki w formacie JSON (opcjonalne)'],
            'body' => ['type' => 'string', 'description' => 'Treść zapytania (opcjonalne)'],
        ]);

        $this->registerTool('get_weather', 'Pobiera informacje o pogodzie dla miasta', [
            'city' => ['type' => 'string', 'description' => 'Nazwa miasta'],
        ]);

        $this->logger->info("HTTP Server tools registered", ['count' => count($this->tools)]);
    }

    private function registerTool($name, $description, $parameters)
    {
        // Określ, które parametry są opcjonalne na podstawie nazwy narzędzia
        $optionalParams = [];
        if ($name === 'list_files') {
            $optionalParams = ['path'];
        } elseif ($name === 'http_request') {
            $optionalParams = ['method', 'headers', 'body'];
        }

        $requiredParams = array_diff(array_keys($parameters), $optionalParams);

        $this->tools[$name] = [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $parameters,
                'required' => array_values($requiredParams),
            ],
        ];
    }

    public function executeTool($name, $arguments)
    {
        $startTime = microtime(true);

        try {
            $result = $this->executeToolLogic($name, $arguments);

            $duration = microtime(true) - $startTime;
            $this->monitoring->recordToolExecution($name, $duration, true);

            $this->logger->info("Tool executed successfully", [
                'tool' => $name,
                'duration' => $duration,
                'arguments' => $arguments,
            ]);

            return $result;
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->monitoring->recordToolExecution($name, $duration, false);

            $this->logger->error("Tool execution failed", [
                'tool' => $name,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            throw $e;
        }
    }

    private function executeToolLogic($name, $arguments)
    {
        switch ($name) {
            case 'hello':
                $userName = $arguments['name'] ?? 'Nieznajomy';

                return "Cześć, $userName! Miło Cię poznać.";

            case 'get_time':
                return "Aktualny czas: ".date('Y-m-d H:i:s');

            case 'calculate':
                $a = floatval($arguments['a'] ?? 0);
                $b = floatval($arguments['b'] ?? 0);
                $op = $arguments['operation'] ?? '';

                switch ($op) {
                    case 'add':
                        return "Wynik: ".($a + $b);
                    case 'subtract':
                        return "Wynik: ".($a - $b);
                    case 'multiply':
                        return "Wynik: ".($a * $b);
                    case 'divide':
                        if ($b == 0) {
                            return "Błąd: Dzielenie przez zero";
                        }

                        return "Wynik: ".($a / $b);
                    default:
                        return "Nieznana operacja: $op";
                }

            case 'list_files':
                $path = $arguments['path'] ?? '.';
                $basePath = dirname(__DIR__); // Katalog główny projektu (poprawna ścieżka)

                // Obsługa ścieżek z ~ (home directory)
                if (str_starts_with($path, '~/')) {
                    $homePath = getenv('HOME') ?: getenv('USERPROFILE');
                    $fullPath = $homePath.substr($path, 1);
                } elseif (realpath($path)) {
                    // Ścieżka absolutna - użyj bezpośrednio
                    $fullPath = realpath($path);
                } else {
                    // Ścieżka względna - połącz z basePath
                    $fullPath = realpath($basePath.'/'.ltrim($path, './'));
                }

                // Debug info
                $this->logger->debug("list_files path resolution", [
                    'input_path' => $arguments['path'] ?? '.',
                    'base_path' => $basePath,
                    'full_path' => $fullPath,
                ]);

                if (!$fullPath || !str_starts_with($fullPath, $basePath)) {
                    throw new Exception('Dostęp do katalogu zabroniony! Ścieżka wykracza poza dozwolony katalog.');
                }

                if (!is_dir($fullPath)) {
                    throw new Exception("To nie jest katalog: $path");
                }

                $files = scandir($fullPath);
                $result = "Pliki w katalogu: $path\n\n";

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $filePath = $fullPath.'/'.$file;
                    $type = is_dir($filePath) ? '[DIR]' : '[FILE]';
                    $size = is_file($filePath) ? filesize($filePath) : 0;

                    $result .= "$type $file";
                    if (is_file($filePath)) {
                        $result .= " (".number_format($size)." bajtów)";
                    }
                    $result .= "\n";
                }

                return $result;

            case 'read_file':
                $path = $arguments['path'] ?? '';
                $basePath = dirname(__DIR__);

                if (empty($path)) {
                    throw new Exception('Ścieżka do pliku jest wymagana');
                }

                // Obsługa ścieżek z ~
                if (str_starts_with($path, '~/')) {
                    $homePath = getenv('HOME') ?: getenv('USERPROFILE');
                    $fullPath = $homePath.substr($path, 1);
                } elseif (realpath($path)) {
                    // Ścieżka absolutna - użyj bezpośrednio
                    $fullPath = realpath($path);
                } else {
                    // Ścieżka względna - połącz z basePath
                    $fullPath = realpath($basePath.'/'.ltrim($path, './'));
                }

                if (!$fullPath || !str_starts_with($fullPath, $basePath)) {
                    throw new Exception('Dostęp do pliku zabroniony! Ścieżka wykracza poza dozwolony katalog.');
                }

                if (!file_exists($fullPath)) {
                    throw new Exception("Plik nie istnieje: $path");
                }

                $content = file_get_contents($fullPath);
                $size = filesize($fullPath);

                return "Plik: $path\nRozmiar: $size bajtów\nZawartość:\n---\n".$content;

            case 'write_file':
                $path = $arguments['path'] ?? '';
                $content = $arguments['content'] ?? '';
                $basePath = dirname(__DIR__);

                if (empty($path)) {
                    throw new Exception('Ścieżka do pliku jest wymagana');
                }

                // Obsługa ścieżek z ~
                if (str_starts_with($path, '~/')) {
                    $homePath = getenv('HOME') ?: getenv('USERPROFILE');
                    $fullPath = $homePath.substr($path, 1);
                } else {
                    // Ścieżka względna lub absolutna
                    $fullPath = $basePath.'/'.ltrim($path, './');
                }

                // Normalizuj ścieżkę i sprawdź bezpieczeństwo
                $fullPath = str_replace('//', '/', $fullPath);
                $dirPath = dirname($fullPath);

                if (!str_starts_with(realpath($dirPath), $basePath)) {
                    throw new Exception('Dostęp do katalogu zabroniony! Ścieżka wykracza poza dozwolony katalog.');
                }

                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $bytes = file_put_contents($fullPath, $content);

                return "Zapisano plik: $path\nZapisano bajtów: $bytes";

            case 'system_info':
                return "=== INFORMACJE O SYSTEMIE ===\n\n".
                    "System operacyjny: ".PHP_OS."\n".
                    "Wersja PHP: ".PHP_VERSION."\n".
                    "Architektura: ".php_uname('m')."\n".
                    "Hostname: ".gethostname()."\n".
                    "Pamięć limit: ".ini_get('memory_limit')."\n".
                    "Maksymalny czas wykonania: ".ini_get('max_execution_time')."s\n".
                    "Katalog roboczy: ".getcwd()."\n".
                    "Załadowane rozszerzenia: ".implode(', ', get_loaded_extensions());

            case 'json_parse':
                try {
                    $json = $arguments['json'] ?? '';
                    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                    $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                    return "=== SPARSOWANY JSON ===\n\n".
                        "Typ główny: ".gettype($data)."\n".
                        (is_array($data) ? "Liczba elementów: ".count($data)."\n" : "").
                        "\nSformatowany JSON:\n---\n".
                        $pretty;
                } catch (JsonException $e) {
                    return "Błąd parsowania JSON:\n".$e->getMessage();
                }

            case 'http_request':
                $url = $arguments['url'] ?? '';
                $method = strtoupper($arguments['method'] ?? 'GET');
                $headers = $arguments['headers'] ?? '{}';
                $body = $arguments['body'] ?? '';

                if (empty($url)) {
                    return "Błąd: URL jest wymagany";
                }

                try {
                    // Parsowanie nagłówków z JSON
                    $headersArray = json_decode($headers, true) ?? [];
                    if (!is_array($headersArray)) {
                        $headersArray = [];
                    }

                    // Konfiguracja klienta HTTP
                    $config = [
                        'timeout' => 10,
                        'allow_redirects' => true,
                        'headers' => array_merge([
                            'User-Agent' => 'PHP-MCP-Server/2.1',
                        ], $headersArray),
                    ];

                    if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                        $config['body'] = $body;
                    }

                    // Używamy cURL zamiast Guzzle dla uniknięcia dodatkowych zależności
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

                    if (!empty($headersArray)) {
                        $headerLines = [];
                        foreach ($headersArray as $key => $value) {
                            $headerLines[] = "$key: $value";
                        }
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
                    }

                    if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                    }

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = curl_error($ch);
                    curl_close($ch);

                    if ($error) {
                        return "Błąd HTTP: $error";
                    }

                    return "=== ODPOWIEDŹ HTTP ===\n\n".
                        "URL: $url\n".
                        "Metoda: $method\n".
                        "Status: $httpCode\n".
                        "Rozmiar odpowiedzi: ".strlen($response)." bajtów\n\n".
                        "Treść odpowiedzi:\n---\n".
                        substr($response, 0, 5000).(strlen($response) > 5000 ? "\n\n... (obcięte)" : "");
                } catch (Exception $e) {
                    return "Błąd wykonania zapytania HTTP: ".$e->getMessage();
                }

            case 'get_weather':
                $city = $arguments['city'] ?? '';
                if (empty($city)) {
                    return "Błąd: Nazwa miasta jest wymagana";
                }

                // Symulacja odpowiedzi pogodowej (w prawdziwej implementacji użylibyśmy API)
                $weatherConditions = ['słonecznie', 'pochmurnie', 'deszczowo', 'śnieży', 'wietrznie'];
                $condition = $weatherConditions[array_rand($weatherConditions)];
                $temp = rand(-10, 30);
                $humidity = rand(30, 90);
                $windSpeed = rand(0, 30);

                return "=== POGODA DLA MIASTA: ".strtoupper($city)." ===\n\n".
                    "Stan pogody: $condition\n".
                    "Temperatura: {$temp}°C\n".
                    "Wilgotność: {$humidity}%\n".
                    "Prędkość wiatru: {$windSpeed} km/h\n".
                    "Data aktualizacji: ".date('Y-m-d H:i:s')."\n\n".
                    "*Uwaga: Dane symulowane - w prawdziwej implementacji użyto by zewnętrznego API pogodowego";

            default:
                throw new Exception("Nieznane narzędzie: $name");
        }
    }

    public function handleHTTP()
    {
        // Obsługa GET - pokazuje dostępne endpointy
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return [
                'message' => 'Testowy serwer MCP przez HTTP',
                'endpoints' => [
                    'GET /' => 'Ta informacja',
                    'GET /tools' => 'Lista dostępnych narzędzi',
                    'POST /call' => 'Wywołanie narzędzia (JSON: {"tool": "nazwa", "arguments": {}})',
                ],
                'example' => [
                    'url' => '/call',
                    'method' => 'POST',
                    'body' => [
                        'tool' => 'hello',
                        'arguments' => ['name' => 'Jan'],
                    ],
                ],
            ];
        }

        // Obsługa POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return ['error' => 'Nieprawidłowy JSON'];
            }

            $tool = $data['tool'] ?? '';
            $arguments = $data['arguments'] ?? [];

            if (!isset($this->tools[$tool])) {
                return ['error' => "Nieznane narzędzie: $tool"];
            }

            $result = $this->executeTool($tool, $arguments);

            return ['result' => $result];
        }

        return ['error' => 'Nieobsługiwana metoda HTTP'];
    }

    public function getTools(): array
    {
        return ['tools' => array_values($this->tools)];
    }
}

