<?php

namespace App;

use App\Config\ServerConfig;
use App\Controllers\AdminController;
use App\Controllers\SecretController;
use App\Controllers\ToolsController;
use App\Interfaces\TemplateRendererInterface;
use App\Interfaces\ToolExecutorInterface;
use App\Routing\ApiRoutes;
use App\Services\AdminAuthService;
use App\Services\SecretManagerService;
use App\Services\ServerService;
use App\Services\SystemInfoCollector;
use App\Services\TemplateRenderer;
use App\Services\ToolRegistry;
use DI\ContainerBuilder;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use function DI\create;
use function DI\factory;
use function DI\get;

// Upewniamy się, że import jest poprawny

class AppContainer
{
    public static function build(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->useAttributes(true);

        $config = require __DIR__.'/../config/server.php';

        $definitions = [
            'config' => $config,
            ServerConfig::class => create(ServerConfig::class)->constructor(get('config')),

            LoggerInterface::class => factory(function (ContainerInterface $c) {
                $config = $c->get('config');
                $logger = new Logger('mcp-server');
                $fileHandler = new RotatingFileHandler(
                    $config['logging']['file'],
                    $config['logging']['max_files'] ?? 5,
                    $config['logging']['level'] ?? Logger::INFO
                );
                $formatter = new LineFormatter(
                    "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                    "Y-m-d H:i:s"
                );
                $fileHandler->setFormatter($formatter);
                $logger->pushHandler($fileHandler);

                return $logger;
            }),

            // Definicja wskazuje teraz na poprawną klasę
            Services\MonitoringService::class => create(Services\MonitoringService::class)
                ->constructor(get(LoggerInterface::class)),

            MCPServerHTTP::class => create(MCPServerHTTP::class)
                ->constructor(get(LoggerInterface::class), get(Services\MonitoringService::class)),

            ServerService::class => create(ServerService::class)
                ->constructor(get(ServerConfig::class)),

            ToolsController::class => create(ToolsController::class)
                ->constructor(get(ServerConfig::class), get(LoggerInterface::class), get(ToolExecutorInterface::class)),

            SecretManagerService::class => create(SecretManagerService::class)
                ->constructor(get(LoggerInterface::class)),

            SecretController::class => create(SecretController::class)
                ->constructor(get(ServerConfig::class), get(LoggerInterface::class), get(SecretManagerService::class)),

            AdminAuthService::class => create(AdminAuthService::class)
                ->constructor(get(LoggerInterface::class)),

            TemplateRendererInterface::class => create(TemplateRenderer::class),

            SystemInfoCollector::class => create(SystemInfoCollector::class),

            ToolExecutorInterface::class => create(ToolRegistry::class),

            AdminController::class => create(AdminController::class)
                ->constructor(
                    get(ServerConfig::class),
                    get(LoggerInterface::class),
                    get(AdminAuthService::class),
                    get(TemplateRendererInterface::class),
                    get(SystemInfoCollector::class)
                ),

            App::class => factory(function (ContainerInterface $c) {
                $app = AppFactory::createFromContainer($c);
                ApiRoutes::register($app);
                $app->addBodyParsingMiddleware();
                $app->addRoutingMiddleware();
                $app->addErrorMiddleware(true, true, true, $c->get(LoggerInterface::class));

                return $app;
            }),
        ];

        $builder->addDefinitions($definitions);

        return $builder->build();
    }
}
