<?php

if (PHP_SAPI === 'cli-server') {
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

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
