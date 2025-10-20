<?php
namespace App;

use Exception;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use const STDIN;
use const STDOUT;

class MCPServer {
    private $tools = [];
    private $serverInfo = [
        'name' => 'enhanced-php-mcp-server',
        'version' => '2.1.0'
    ];
    private LoggerInterface $logger;
    private MonitoringService $monitoring;

    public function __construct(LoggerInterface $logger, MonitoringService $monitoring) {
        $this->logger = $logger;
        $this->monitoring = $monitoring;

        $this->logger->info("MCP Server initializing", [
            'version' => $this->serverInfo['version'],
            'php_version' => PHP_VERSION
        ]);

        $this->registerTools();
    }

    private function registerTools(): void {
        $this->registerTool('hello', 'Zwraca powitanie', [
            'name' => ['type' => 'string', 'description' => 'Imię do powitania']
        ]);

        $this->registerTool('get_time', 'Zwraca aktualny czas', []);

        $this->registerTool('calculate', 'Wykonuje proste obliczenia', [
            'operation' => ['type' => 'string', 'description' => 'Operacja: add, subtract, multiply, divide'],
            'a' => ['type' => 'number', 'description' => 'Pierwsza liczba'],
            'b' => ['type' => 'number', 'description' => 'Druga liczba']
        ]);

        $this->registerTool('read_file', 'Odczytuje zawartość pliku', [
            'path' => ['type' => 'string', 'description' => 'Ścieżka do pliku']
        ]);

        $this->registerTool('write_file', 'Zapisuje zawartość do pliku', [
            'path' => ['type' => 'string', 'description' => 'Ścieżka do pliku'],
            'content' => ['type' => 'string', 'description' => 'Zawartość do zapisania']
        ]);

        $this->registerTool('list_files', 'Wyświetla listę plików w katalogu', [
            'path' => ['type' => 'string', 'description' => 'Ścieżka do katalogu (opcjonalne)']
        ]);

        $this->registerTool('system_info', 'Zwraca informacje o systemie', []);

        $this->registerTool('json_parse', 'Parsuje i formatuje JSON', [
            'json' => ['type' => 'string', 'description' => 'Tekst JSON do sparsowania']
        ]);

        $this->registerTool('get_weather', 'Pobiera informacje o pogodzie', [
            'city' => ['type' => 'string', 'description' => 'Nazwa miasta']
        ]);

        $this->logger->info("Tools registered", ['count' => count($this->tools)]);
    }

    private function registerTool($name, $description, $parameters) {
        $this->tools[$name] = [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $parameters,
                'required' => array_keys($parameters)
            ]
        ];
    }

    private function handleRequest($request): array
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        try {
            switch ($method) {
                case 'initialize':
                    return $this->createResponse($id, [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => (object)[]
                        ],
                        'serverInfo' => $this->serverInfo
                    ]);

                case 'tools/list':
                    return $this->createResponse($id, [
                        'tools' => array_values($this->tools)
                    ]);

                case 'tools/call':
                    $toolName = $params['name'] ?? '';
                    $arguments = $params['arguments'] ?? [];
                    $result = $this->executeTool($toolName, $arguments);

                    return $this->createResponse($id, [
                        'content' => [
                            ['type' => 'text', 'text' => $result]
                        ]
                    ]);

                default:
                    throw new Exception("Nieznana metoda: $method");
            }
        } catch (Exception $e) {
            return $this->createErrorResponse($id, $e->getMessage());
        }
    }

    private function executeTool($name, $arguments): string
    {
        $startTime = microtime(true);

        try {
            $result = $this->executeToolLogic($name, $arguments);

            $duration = microtime(true) - $startTime;
            $this->monitoring->recordToolExecution($name, $duration, true);

            $this->logger->info("CLI tool executed successfully", [
                'tool' => $name,
                'duration' => $duration,
                'arguments' => $arguments
            ]);

            return $result;
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->monitoring->recordToolExecution($name, $duration, false);

            $this->logger->error("CLI tool execution failed", [
                'tool' => $name,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'arguments' => $arguments
            ]);

            throw $e;
        }
    }

    private function executeToolLogic($name, $arguments): string
    {
        switch ($name) {
            case 'hello':
                $userName = $arguments['name'] ?? 'Nieznajomy';
                return "Cześć, $userName! Miło Cię poznać. 👋";

            case 'get_time':
                $now = new \DateTime();
                return "Aktualny czas: ".$now->format('Y-m-d H:i:s')."\n".
                    "Dzień tygodnia: ".$now->format('l')."\n".
                    "Timestamp: ".$now->getTimestamp();

            case 'calculate':
                $a = floatval($arguments['a'] ?? 0);
                $b = floatval($arguments['b'] ?? 0);
                $op = $arguments['operation'] ?? '';

                switch ($op) {
                    case 'add':
                        return "Wynik: " . ($a + $b);
                    case 'subtract':
                        return "Wynik: " . ($a - $b);
                    case 'multiply':
                        return "Wynik: " . ($a * $b);
                    case 'divide':
                        if ($b == 0) return "Błąd: Dzielenie przez zero";
                        return "Wynik: " . ($a / $b);
                    default:
                        return "Nieznana operacja: $op";
                }

            case 'read_file':
                $path = $arguments['path'] ?? '';
                $basePath = __DIR__;
                $fullPath = realpath($basePath.'/'.$path);

                if (!$fullPath || !str_starts_with($fullPath, $basePath)) {
                    throw new Exception('Dostęp do pliku zabroniony!');
                }

                if (!file_exists($fullPath)) {
                    throw new Exception("Plik nie istnieje: $path");
                }

                $content = file_get_contents($fullPath);
                $size = filesize($fullPath);

                return "Plik: $path\nRozmiar: $size bajtów\nZawartość:\n---\n" . $content;

            case 'write_file':
                $path = $arguments['path'] ?? '';
                $content = $arguments['content'] ?? '';
                $basePath = __DIR__;
                $fullPath = $basePath.'/'.$path;

                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $bytes = file_put_contents($fullPath, $content);
                return "Zapisano plik: $path\nZapisano bajtów: $bytes";

            case 'list_files':
                $path = $arguments['path'] ?? '.';
                $basePath = __DIR__;
                $fullPath = realpath($basePath.'/'.$path);

                if (!$fullPath || !str_starts_with($fullPath, $basePath)) {
                    throw new Exception('Dostęp do katalogu zabroniony!');
                }

                if (!is_dir($fullPath)) {
                    throw new Exception("To nie jest katalog: $path");
                }

                $files = scandir($fullPath);
                $result = "Pliki w katalogu: $path\n\n";

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;

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
                } catch (\JsonException $e) {
                    return "Błąd parsowania JSON:\n".$e->getMessage();
                }

            case 'get_weather':
                $city = $arguments['city'] ?? '';
                if (empty($city)) {
                    return "Nie podano nazwy miasta";
                }

                // Prosta symulacja odpowiedzi pogodowej
                return "=== POGODA ===\n\n".
                    "Miasto: $city\n".
                    "Temperatura: ".rand(-10, 30)."°C\n".
                    "Wilgotność: ".rand(30, 90)."%\n".
                    "Prędkość wiatru: ".rand(0, 50)." km/h\n".
                    "Status: ".['Słonecznie', 'Pochmurno', 'Deszcz', 'Śnieg'][array_rand(['Słonecznie', 'Pochmurno', 'Deszcz', 'Śnieg'])]."\n".
                    "(Uwaga: To jest symulacja - prawdziwe API wymaga połączenia z internetem)";

            default:
                throw new Exception("Nieznane narzędzie: $name");
        }
    }

    private function createResponse($id, $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result
        ];
    }

    private function createErrorResponse($id, $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => -32603,
                'message' => $message
            ]
        ];
    }

    public function run()
    {
        // Użyj pętli zdarzeń ReactPHP do obsługi nieblokującego I/O
        $loop = Loop::get();
        // Stwórz nieblokujące strumienie dla STDIN i STDOUT
        $input = new ReadableResourceStream(STDIN, $loop);
        $output = new WritableResourceStream(STDOUT, $loop);

        $this->logger->info("Enhanced MCP Server started with ReactPHP event loop");

        // Nasłuchuj na zdarzenie 'data', które wystąpi, gdy pojawią się dane na wejściu
        $input->on('data', function ($chunk) use ($output) {
            // Pojedynczy 'chunk' danych może zawierać wiele obiektów JSON, więc przetwarzamy je wszystkie
            $lines = explode("\n", trim($chunk));
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $this->logger->debug("Received request", ['data' => $line]);
                $request = json_decode($line, true);

                // Obsługa błędów parsowania JSON
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error("JSON parse error", ['error' => json_last_error_msg(), 'data' => $line]);
                    $errorResponse = $this->createErrorResponse(null, 'Parse error');
                    $output->write(json_encode($errorResponse) . "\n");
                    continue;
                }

                try {
                    $response = $this->handleRequest($request);
                    $responseJson = json_encode($response);

                    $this->logger->debug("Sending response", ['response' => $responseJson]);
                    $output->write($responseJson . "\n");
                } catch (Exception $e) {
                    $this->logger->error("Request handling failed", ['error' => $e->getMessage()]);
                    $errorResponse = $this->createErrorResponse($request['id'] ?? null, $e->getMessage());
                    $output->write(json_encode($errorResponse) . "\n");
                }
            }
        });

        $input->on('end', function () {
            $this->logger->info("Input stream closed. Shutting down server.");
        });

        // Uruchom pętlę zdarzeń. Skrypt będzie teraz działał w sposób ciągły, reagując na zdarzenia.
        $loop->run();
    }
}
