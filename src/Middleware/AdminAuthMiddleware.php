<?php

namespace App\Middleware;

use App\Services\AdminAuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;
use Slim\Routing\RouteContext;

class AdminAuthMiddleware
{
    private AdminAuthService $authService;
    private LoggerInterface $logger;

    public function __construct(AdminAuthService $authService, LoggerInterface $logger)
    {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $sessionId = $this->getSessionId($request);

        if (!$sessionId) {
            $this->logger->warning('Admin access attempt without session', [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'path' => $request->getUri()->getPath(),
            ]);

            return $this->createUnauthorizedResponse($request);
        }

        $sessionData = $this->authService->validateSession($sessionId);
        if (!$sessionData) {
            $this->logger->warning('Admin access attempt with invalid session', [
                'session_id' => substr($sessionId, 0, 8).'...',
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'path' => $request->getUri()->getPath(),
            ]);

            return $this->createUnauthorizedResponse($request);
        }

        // Add user data to request
        $request = $request->withAttribute('admin_user', $sessionData);
        $request = $request->withAttribute('session_id', $sessionId);

        return $handler->handle($request);
    }

    private function getSessionId(Request $request): ?string
    {
        // Try Authorization header first (Bearer token)
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        // Try cookie
        $cookies = $request->getCookieParams();

        return $cookies['admin_session'] ?? null;
    }

    private function createUnauthorizedResponse(Request $request): Response
    {
        $routeContext = RouteContext::fromRequest($request);
        $routeParser = $routeContext->getRouteParser();

        // Check if this is an API request
        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/admin/api/')) {
            // Return JSON for API requests
            $response = new \Slim\Psr7\Response();
            $response->getBody()->write(json_encode([
                'success' => false,
                'error' => [
                    'message' => 'Authentication required',
                    'code' => 'AUTH_REQUIRED',
                ],
            ]));

            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        // Redirect to login for web requests
        $response = new \Slim\Psr7\Response();
        $response = $response->withHeader('Location', '/admin/login')->withStatus(302);

        return $response;
    }
}