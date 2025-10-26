<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ErrorResponse;
use App\Interfaces\TemplateRendererInterface;
use App\Services\AdminAuthService;
use App\Services\AdminDashboardService;
use App\Services\SystemInfoCollector;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class AdminController extends BaseController
{
    private AdminAuthService $authService;
    private TemplateRendererInterface $templateRenderer;
    private SystemInfoCollector $systemInfoCollector;
    private AdminDashboardService $dashboardService;

    public function __construct(
        ServerConfig $config,
        LoggerInterface $logger,
        AdminAuthService $authService,
        TemplateRendererInterface $templateRenderer,
        SystemInfoCollector $systemInfoCollector,
        AdminDashboardService $dashboardService
    ) {
        parent::__construct($config, $logger);
        $this->authService = $authService;
        $this->templateRenderer = $templateRenderer;
        $this->systemInfoCollector = $systemInfoCollector;
        $this->dashboardService = $dashboardService;
    }

    /**
     * Landing page / home page
     */
    public function landingPage(Request $request, Response $response): Response
    {
        $landingPage = $this->getLandingPageContent();
        $response->getBody()->write($landingPage);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Admin login page
     */
    public function loginPage(Request $request, Response $response): Response
    {
        // If already authenticated, redirect to dashboard
        if ($this->authService->isAuthenticated()) {
            return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
        }

        $loginPage = $this->getLoginPageContent();
        $response->getBody()->write($loginPage);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Process admin login
     */
    public function login(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->errorResponse($response, ErrorResponse::badRequest('Username and password are required'));
        }

        $username = trim($data['username']);
        $password = trim($data['password']);

        if ($this->authService->authenticate($username, $password)) {
            $sessionId = $this->authService->createSession($username);

            // Set cookie
            $response = $response->withHeader(
                'Set-Cookie',
                "admin_session={$sessionId}; Path=/; HttpOnly; SameSite=Strict; Max-Age=28800"
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Login successful',
                'redirect' => '/admin/dashboard',
                'session_id' => $sessionId,
            ]);
        }

        return $this->errorResponse($response, new ErrorResponse('Invalid username or password', 401));
    }

    /**
     * Admin dashboard page
     */
    public function dashboard(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $response->withHeader('Location', '/admin/login')->withStatus(302);
        }

        $user = $this->authService->getCurrentUser();
        $dashboard = $this->getDashboardContent($user);
        $response->getBody()->write($dashboard);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Admin logout
     */
    public function logout(Request $request, Response $response): Response
    {
        $sessionId = $this->authService->getSessionFromRequest();
        if ($sessionId) {
            $this->authService->deleteSession($sessionId);
        }

        // Clear cookie
        $response = $response->withHeader(
            'Set-Cookie',
            "admin_session=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0"
        );

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Logged out successfully',
            'redirect' => '/',
        ]);
    }

    /**
     * Get current admin user info
     */
    public function getCurrentUser(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->errorResponse($response, new ErrorResponse('Not authenticated', 401));
        }

        $user = $this->authService->getCurrentUser();
        if (!$user) {
            return $this->errorResponse($response, new ErrorResponse('Session invalid', 401));
        }

        // Remove sensitive data
        unset($user['ip_address'], $user['user_agent']);

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $user,
        ]);
    }

    /**
     * Change admin password
     */
    public function changePassword(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->errorResponse($response, new ErrorResponse('Not authenticated', 401));
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['old_password']) || !isset($data['new_password'])) {
            return $this->errorResponse(
                $response,
                ErrorResponse::badRequest('Old password and new password are required')
            );
        }

        if ($this->authService->changePassword($data['old_password'], $data['new_password'])) {
            $this->logger->info('Admin password changed successfully');

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Password changed successfully',
            ]);
        }

        return $this->errorResponse($response, ErrorResponse::badRequest('Invalid old password'));
    }

    /**
     * Get admin configuration status
     */
    public function getConfig(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->errorResponse($response, new ErrorResponse('Not authenticated', 401));
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => [
                'configured' => $this->authService->isConfigured(),
                'admin_username' => $this->authService->getAdminUsername(),
                'session_active' => $this->authService->isAuthenticated(),
            ],
        ]);
    }

    /**
     * Get detailed system information
     */
    public function getSystemInfo(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->errorResponse($response, new ErrorResponse('Not authenticated', 401));
        }

        $systemInfo = $this->systemInfoCollector->collect();
        $systemData = $systemInfo->toArray();

        // Add MCP server specific info
        $systemData['mcp_server'] = [
            'name' => $this->config->getName(),
            'version' => $this->config->getVersion(),
            'description' => $this->config->getDescription(),
            'debug_mode' => $this->config->isDebugMode(),
            'log_level' => $this->config->getLogLevel(),
            'tools_enabled' => count($this->config->getEnabledTools()),
            'security_paths' => $this->config->getAllowedPaths(),
        ];

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $systemData,
        ]);
    }

    /**
     * Generate login page HTML
     */
    private function getLoginPageContent(): string
    {
        $templatePath = __DIR__ . '/../../templates/views/admin/login.php';
        return $this->templateRenderer->render($templatePath);
    }

    /**
     * Generate admin dashboard HTML
     */
    private function getDashboardContent(array $user): string
    {
        $templatePath = __DIR__ . '/../../templates/views/admin/dashboard.php';
        return $this->templateRenderer->render($templatePath, ['user' => $user]);
    }

    /**
     * Get dashboard data for API
     */
    public function getDashboardData(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->errorResponse($response, new ErrorResponse('Not authenticated', 401));
        }

        try {
            $data = $this->dashboardService->getDashboardData();
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $data,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, 'Failed to get dashboard data', 500);
        }
    }

    /**
     * Get health check for dashboard
     */
    public function getHealthCheck(Request $request, Response $response): Response
    {
        if (!$this->authService->isAuthenticated()) {
            return $this->errorResponse($response, new ErrorResponse('Not authenticated', 401));
        }

        try {
            $health = $this->dashboardService->performHealthCheck();
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $health,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, 'Health check failed', 500);
        }
    }

    /**
     * Generate landing page HTML
     */
    private function getLandingPageContent(): string
    {
        $templatePath = __DIR__ . '/../../templates/views/loading.php';
        return $this->templateRenderer->render($templatePath);
    }
}