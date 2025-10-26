<?php

require __DIR__ . '/vendor/autoload.php';

use App\{AppContainer, MCPServer};
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerInterface;
use Slim\App;

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

        /** @var App $app */
        $app = $container->get(App::class);

        // Uruchamiamy aplikację Slim
        $app->run();
    }

} catch (Exception $e) {
    // Logujemy błąd krytyczny
    $logMessage = "Critical error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($logMessage);

    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Błąd krytyczny serwera: " . $e->getMessage() . "\n");
        exit(1);
    } else {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
    }
} catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
    $logMessage = "Critical error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
    error_log($logMessage);
}
