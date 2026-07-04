<?php

// Zaciągamy instancję kontenera z bezpiecznego pliku bootstrap
$container = require_once __DIR__ . '/bootstrap/app.php';

use Psr\Log\LoggerInterface;

if ($container) {
    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);
    $logger->info("Starting MCP Server in CLI mode");

    try {
        // Docelowe miejsce na inicjalizację MCPServer (adaptera STDIN/STDOUT)
        echo "Serwer MCP w trybie CLI. Adapter oczekuje na implementację.\n";
        
        // $server = $container->get(MCPServer::class);
        // $server->run();
    } catch (\Throwable $e) {
        $logger->error("Błąd w trybie CLI: " . $e->getMessage());
        fwrite(STDERR, "Błąd: " . $e->getMessage() . "\n");
    }
}
