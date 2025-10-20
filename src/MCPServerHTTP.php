<?php

namespace App;

use Psr\Log\LoggerInterface;

class MCPServerHTTP {
    private array $tools = [];
    private LoggerInterface $logger;
    private MonitoringService $monitoring;

    public function __construct(LoggerInterface $logger, MonitoringService $monitoring) {
        $this->logger = $logger;
        $this->monitoring = $monitoring;

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

        $this->logger->info("HTTP Server tools registered", ['count' => count($this->tools)]);
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

    public function executeTool($name, $arguments) {
        $startTime = microtime(true);

        try {
            $result = $this->executeToolLogic($name, $arguments);

            $duration = microtime(true) - $startTime;
            $this->monitoring->recordToolExecution($name, $duration, true);

            $this->logger->info("Tool executed successfully", [
                'tool' => $name,
                'duration' => $duration,
                'arguments' => $arguments
            ]);

            return $result;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            $this->monitoring->recordToolExecution($name, $duration, false);

            $this->logger->error("Tool execution failed", [
                'tool' => $name,
                'duration' => $duration,
                'error' => $e->getMessage(),
                'arguments' => $arguments
            ]);

            throw $e;
        }
    }

    private function executeToolLogic($name, $arguments) {
        switch ($name) {
            case 'hello':
                $userName = $arguments['name'] ?? 'Nieznajomy';
                return "Cześć, $userName! Miło Cię poznać.";

            case 'get_time':
                return "Aktualny czas: " . date('Y-m-d H:i:s');

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

            default:
                throw new \Exception("Nieznane narzędzie: $name");
        }
    }

    public function handleHTTP() {
        // Obsługa GET - pokazuje dostępne endpointy
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return [
                'message' => 'Testowy serwer MCP przez HTTP',
                'endpoints' => [
                    'GET /' => 'Ta informacja',
                    'GET /tools' => 'Lista dostępnych narzędzi',
                    'POST /call' => 'Wywołanie narzędzia (JSON: {"tool": "nazwa", "arguments": {}})'
                ],
                'example' => [
                    'url' => '/call',
                    'method' => 'POST',
                    'body' => [
                        'tool' => 'hello',
                        'arguments' => ['name' => 'Jan']
                    ]
                ]
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

    public function getTools() {
        return ['tools' => array_values($this->tools)];
    }
}

