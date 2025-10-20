<?php

namespace App;

use DI\Container;
use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use function DI\create;
use function DI\get;
use function DI\factory;

class AppContainer {
    private static ?ContainerInterface $container = null;

    public static function build(): ContainerInterface {
        if (self::$container !== null) {
            return self::$container;
        }

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(true);

        // Ładuj konfigurację
        $config = require __DIR__ . '/../config/server.php';

        // Definicje serwisów
        $definitions = [
            // Konfiguracja
            'config' => $config,

            // Logger - Monolog z rotacją plików
            LoggerInterface::class => factory(function (ContainerInterface $c) {
                $config = $c->get('config');
                $logger = new Logger('mcp-server');

                // Handler do pliku z rotacją
                $fileHandler = new RotatingFileHandler(
                    $config['logging']['file'],
                    $config['logging']['max_files'] ?? 5,
                    $config['logging']['level'] ?? Logger::INFO
                );

                // Format logów
                $formatter = new LineFormatter(
                    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
                    "Y-m-d H:i:s"
                );
                $fileHandler->setFormatter($formatter);
                $logger->pushHandler($fileHandler);

                // Handler do konsoli (tylko w trybie CLI)
                if (php_sapi_name() === 'cli') {
                    $consoleHandler = new StreamHandler('php://stdout', Logger::INFO);
                    $consoleHandler->setFormatter($formatter);
                    $logger->pushHandler($consoleHandler);
                }

                return $logger;
            }),

            // Monitoring Service
            MonitoringService::class => create(MonitoringService::class)
                ->constructor(get(LoggerInterface::class)),

            // HTTP Client
            'http_client' => factory(function (ContainerInterface $c) {
                $config = $c->get('config');
                return new \GuzzleHttp\Client([
                    'timeout' => $config['http']['timeout'] ?? 10,
                    'allow_redirects' => $config['http']['allow_redirects'] ?? true,
                    'headers' => [
                        'User-Agent' => $config['http']['user_agent'] ?? 'PHP-MCP-Server/2.1'
                    ]
                ]);
            }),

            // MCP Server CLI
            MCPServer::class => create(MCPServer::class)
                ->constructor(get(LoggerInterface::class), get(MonitoringService::class)),

            // MCP Server HTTP
            MCPServerHTTP::class => create(MCPServerHTTP::class)
                ->constructor(get(LoggerInterface::class), get(MonitoringService::class)),

            // Slim App dla HTTP API
            \Slim\App::class => factory(function (ContainerInterface $c) {
                $config = $c->get('config');

                // Konfiguracja Slim
                $slimConfig = [
                    'settings' => [
                        'displayErrorDetails' => $config['debug'] ?? false,
                        'logErrors' => true,
                        'logErrorDetails' => true,
                    ]
                ];

                // Tworzenie Slim App z kontenerem DI
                $app = \Slim\Factory\AppFactory::create($c, $slimConfig);

                // Dodaj middleware
                $app->addBodyParsingMiddleware();
                $app->addRoutingMiddleware();
                $app->addErrorMiddleware(true, true, true, $c->get(LoggerInterface::class));

                return $app;
            }),
        ];

        $builder->addDefinitions($definitions);

        try {
            self::$container = $builder->build();
            return self::$container;
        } catch (\Exception $e) {
            throw new \RuntimeException("Failed to build container: " . $e->getMessage());
        }
    }

    public static function getContainer(): ContainerInterface {
        if (self::$container === null) {
            self::build();
        }
        return self::$container;
    }

    public static function reset(): void {
        self::$container = null;
    }
}