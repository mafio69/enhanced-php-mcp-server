<?php

// Zaciągamy instancję kontenera z bezpiecznego, niewidocznego dla świata pliku bootstrap
$container = require_once dirname(__DIR__) . '/bootstrap/app.php';

use Psr\Log\LoggerInterface;
use Slim\App;

if ($container) {
    /** @var LoggerInterface $logger */
    $logger = $container->get(LoggerInterface::class);
    $logger->info("Starting MCP Server in HTTP mode (Slim Framework)");

    /** @var App $app */
    $app = $container->get(App::class);

    // Uruchamiamy cykl życia aplikacji
    $app->run();
}
