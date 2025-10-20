<?php

require __DIR__ . '/vendor/autoload.php';

use App\{AppContainer, MCPServer, MCPServerHTTP};
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

try {
    // Budujemy kontener DI
    $container = AppContainer::build();

    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);

    // Rozróżnienie kontekstu wykonania: CLI vs HTTP
    if (php_sapi_name() === 'cli') {
        $logger->info("Starting MCP Server in CLI mode");

        /** @var MCPServer $server */
        $server = $container->get(MCPServer::class);
        $server->run();
    } else {
        $logger->info("Starting MCP Server in HTTP mode");

        /** @var \Slim\App $app */
        $app = $container->get(\Slim\App::class);

        // Definiujemy trasy Slim
        $app->get('/', function ($request, $response, $args) use ($container) {
            $logger = $container->get(LoggerInterface::class);
            $logger->info("HTTP request to root endpoint");

            $data = [
                'message' => 'MCP Server with Slim Framework',
                'version' => $container->get('config')['server']['version'],
                'endpoints' => [
                    'GET /' => 'This information',
                    'GET /api/tools' => 'List available tools',
                    'GET /api/status' => 'Server status and metrics',
                    'POST /api/tools/call' => 'Execute a tool',
                    'GET /api/logs' => 'Recent log entries',
                    'GET /api/metrics' => 'System metrics'
                ]
            ];

            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/api/tools', function ($request, $response, $args) use ($container) {
            $logger = $container->get(LoggerInterface::class);
            $logger->info("HTTP request to tools endpoint");

            /** @var MCPServerHTTP $httpServer */
            $httpServer = $container->get(MCPServerHTTP::class);
            $tools = $httpServer->getTools();

            $response->getBody()->write(json_encode($tools, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/api/status', function ($request, $response, $args) use ($container) {
            $logger = $container->get(LoggerInterface::class);
            $logger->info("HTTP request to status endpoint");

            /** @var MonitoringService $monitoring */
            $monitoring = $container->get(MonitoringService::class);
            $metrics = $monitoring->getMetrics();

            $data = [
                'status' => 'running',
                'server' => $container->get('config')['server'],
                'metrics' => $metrics
            ];

            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->post('/api/tools/call', function ($request, $response, $args) use ($container) {
            $logger = $container->get(LoggerInterface::class);
            $startTime = microtime(true);

            $data = json_decode($request->getBody()->getContents(), true);
            $tool = $data['tool'] ?? '';
            $arguments = $data['arguments'] ?? [];

            $logger->info("HTTP tool execution request", ['tool' => $tool, 'arguments' => $arguments]);

            try {
                /** @var MCPServerHTTP $httpServer */
                $httpServer = $container->get(MCPServerHTTP::class);
                $result = $httpServer->executeTool($tool, $arguments);

                $duration = microtime(true) - $startTime;
                /** @var MonitoringService $monitoring */
                $monitoring = $container->get(MonitoringService::class);
                $monitoring->recordToolExecution($tool, $duration, true);

                $responseData = ['success' => true, 'result' => $result];
            } catch (\Exception $e) {
                $duration = microtime(true) - $startTime;
                /** @var MonitoringService $monitoring */
                $monitoring = $container->get(MonitoringService::class);
                $monitoring->recordToolExecution($tool, $duration, false);

                $logger->error("Tool execution failed", ['tool' => $tool, 'error' => $e->getMessage()]);
                $responseData = ['success' => false, 'error' => $e->getMessage()];
            }

            $response->getBody()->write(json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/api/logs', function ($request, $response, $args) use ($container) {
            $logger = $container->get(LoggerInterface::class);
            $logger->info("HTTP request to logs endpoint");

            $config = $container->get('config');
            $logFile = $config['logging']['file'];

            if (file_exists($logFile)) {
                $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice($lines, -50); // Ostatnie 50 linii
                $data = ['logs' => $recentLines];
            } else {
                $data = ['logs' => [], 'message' => 'Log file not found'];
            }

            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $app->get('/api/metrics', function ($request, $response, $args) use ($container) {
            $logger = $container->get(LoggerInterface::class);
            $logger->info("HTTP request to metrics endpoint");

            /** @var MonitoringService $monitoring */
            $monitoring = $container->get(MonitoringService::class);
            $metrics = $monitoring->getMetrics();

            $response->getBody()->write(json_encode($metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return $response->withHeader('Content-Type', 'application/json');
        });

        // Uruchamiamy aplikację Slim
        $app->run();
    }

} catch (\Exception $e) {
    // Logujemy błąd krytyczny
    error_log("Critical error: " . $e->getMessage());

    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Błąd krytyczny serwera: " . $e->getMessage() . "\n");
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
    }
}
