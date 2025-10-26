<?php

namespace App\Routing;

use App\Controllers\AdminController;
use App\Controllers\LogsController;
use App\Controllers\SecretController;
use App\Controllers\ServerController;
use App\Controllers\StatusController;
use App\Controllers\ToolsController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class ApiRoutes
{
    public static function register(App $app): void
    {
        // Główny endpoint serwujący landing page lub informacje API
        $app->get('/', function (Request $request, Response $response) use ($app) {
            $container = $app->getContainer();
            $logger = $container->get(LoggerInterface::class);
            $logger->info("HTTP request to root endpoint");

            $acceptHeader = $request->getHeaderLine('Accept');
            if (str_contains($acceptHeader, 'text/html')) {
                // Use AdminController to render the landing page
                $adminController = $container->get(AdminController::class);
                return $adminController->landingPage($request, $response);
            }

            $data = ['message' => 'MCP Server API is running'];
            $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

            return $response->withHeader('Content-Type', 'application/json');
        });

        // Grupa tras API
        $app->group('/api', function (RouteCollectorProxy $group) {
            // --- Przywrócone trasy dla narzędzi ---
            $group->get('/tools', [ToolsController::class, 'listTools']);
            $group->post('/tools/call', [ToolsController::class, 'executeTool']);

            $group->get('/status', [StatusController::class, 'getServerStatus']);
            $group->get('/metrics', [StatusController::class, 'getMetrics']);
            $group->get('/health', [StatusController::class, 'getHealth']);

            $group->get('/logs', [LogsController::class, 'getLogs']);
            $group->delete('/logs', [LogsController::class, 'clearLogs']);
            $group->get('/logs/download', [LogsController::class, 'downloadLogs']);
            $group->get('/logs/stats', [LogsController::class, 'getLogStats']);

        });

        // Admin routes (all routes)
        $app->group('/admin', function (RouteCollectorProxy $group) {
            $group->get('/login', [AdminController::class, 'loginPage']);
            $group->post('/login', [AdminController::class, 'login']);
            $group->get('/dashboard', [AdminController::class, 'dashboard']);
            $group->post('/logout', [AdminController::class, 'logout']);
            $group->get('/user', [AdminController::class, 'getCurrentUser']);
            $group->post('/change-password', [AdminController::class, 'changePassword']);
            $group->get('/config', [AdminController::class, 'getConfig']);
            $group->get('/system-info', [AdminController::class, 'getSystemInfo']);

            // Admin API routes
            $group->group('/api', function (RouteCollectorProxy $apiGroup) {
                // Secret management
                $apiGroup->get('/secrets', [SecretController::class, 'listSecrets']);
                $apiGroup->post('/secrets', [SecretController::class, 'storeSecret']);
                $apiGroup->get('/secrets/{key}', [SecretController::class, 'getSecret']);
                $apiGroup->delete('/secrets/{key}', [SecretController::class, 'deleteSecret']);
                $apiGroup->get('/secrets/{key}/check', [SecretController::class, 'checkSecret']);
                $apiGroup->post('/secrets/encrypt', [SecretController::class, 'encryptValue']);
                $apiGroup->post('/secrets/decrypt', [SecretController::class, 'decryptValue']);
                $apiGroup->post('/secrets/migrate', [SecretController::class, 'migrateSecrets']);

                // Server management
                $apiGroup->post('/servers', [ServerController::class, 'addServer']);
            });
        });

        // Catch-all dla nieznalezionych tras API
        $app->map(['GET', 'POST', 'PUT', 'DELETE'],
            '/api/{routes:.+}',
            function (Request $request, Response $response) {
                $data = ['error' => 'Not Found', 'message' => 'The requested API endpoint does not exist.'];
                $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));

                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            });
    }
}
