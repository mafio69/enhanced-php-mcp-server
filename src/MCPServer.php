<?php
namespace App;

use App\Services\MonitoringService; // Zaktualizowano przestrzeń nazw
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

    // Poprawiono typ parametru
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
                return "Aktualny czas: ".$now->format('Y-m-d H:i:s');

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
        $loop = Loop::get();
        $input = new ReadableResourceStream(STDIN, $loop);
        $output = new WritableResourceStream(STDOUT, $loop);

        $this->logger->info("Enhanced MCP Server started with ReactPHP event loop");

        $input->on('data', function ($chunk) use ($output) {
            $lines = explode("\n", trim($chunk));
            foreach ($lines as $line) {
                if (empty($line)) {
                    continue;
                }

                $this->logger->debug("Received request", ['data' => $line]);
                $request = json_decode($line, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errorResponse = $this->createErrorResponse(null, 'Parse error');
                    $output->write(json_encode($errorResponse) . "\n");
                    continue;
                }

                try {
                    $response = $this->handleRequest($request);
                    $output->write(json_encode($response) . "\n");
                } catch (Exception $e) {
                    $errorResponse = $this->createErrorResponse($request['id'] ?? null, $e->getMessage());
                    $output->write(json_encode($errorResponse) . "\n");
                }
            }
        });

        $loop->run();
    }
}
