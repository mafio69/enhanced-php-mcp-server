<?php

namespace App\Routing;

use App\Controllers\ToolsController;
use App\Controllers\StatusController;
use App\Controllers\LogsController;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

class ApiRoutes
{
    public static function register(App $app): void
    {
        // API routes group
        $app->group('/api', function (RouteCollectorProxy $group) {
            // Tools endpoints
            $group->get('/tools', [ToolsController::class, 'listTools']);
            $group->post('/tools/call', [ToolsController::class, 'executeTool']);
            $group->get('/tools/{tool}/schema', [ToolsController::class, 'getToolSchema']);

            // Status endpoints
            $group->get('/status', [StatusController::class, 'getServerStatus']);
            $group->get('/metrics', [StatusController::class, 'getMetrics']);
            $group->get('/health', [StatusController::class, 'getHealth']);

            // Logs endpoints
            $group->get('/logs', [LogsController::class, 'getLogs']);
            $group->delete('/logs', [LogsController::class, 'clearLogs']);
            $group->get('/logs/download', [LogsController::class, 'downloadLogs']);
            $group->get('/logs/stats', [LogsController::class, 'getLogStats']);
        });

        // Root endpoint - server info
        $app->get('/', function ($request, $response) {
            $response->getBody()->write(json_encode([
                'name' => 'Enhanced PHP MCP Server',
                'version' => '2.1.0',
                'status' => 'running',
                'description' => 'Professional PHP MCP Server with multiple tools',
                'endpoints' => [
                    'tools' => '/api/tools',
                    'execute' => '/api/tools/call',
                    'status' => '/api/status',
                    'metrics' => '/api/metrics',
                    'health' => '/api/health',
                    'logs' => '/api/logs'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json');
        });

        // Catch-all 404 handler
        $app->any('/{path:.*}', function ($request, $response, array $args) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => 'Endpoint not found',
                'path' => $args['path'] ?? '',
                'message' => 'The requested endpoint does not exist',
                'available_endpoints' => [
                    '/' => 'Server information',
                    '/api/tools' => 'List all available tools',
                    '/api/tools/call' => 'Execute a tool (POST)',
                    '/api/status' => 'Server status and metrics',
                    '/api/metrics' => 'Detailed system metrics',
                    '/api/health' => 'Health check endpoint',
                    '/api/logs' => 'View recent logs'
                ],
                'timestamp' => date('Y-m-d H:i:s')
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(404);
        });
    }
}