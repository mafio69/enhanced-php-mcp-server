<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ErrorResponse;
use App\Services\AdminAuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class AdminController extends BaseController
{
    private AdminAuthService $authService;

    public function __construct(ServerConfig $config, LoggerInterface $logger, AdminAuthService $authService)
    {
        parent::__construct($config, $logger);
        $this->authService = $authService;
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

        $systemInfo = $this->collectSystemInfo();

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $systemInfo,
        ]);
    }

    /**
     * Collect comprehensive system information
     */
    private function collectSystemInfo(): array
    {
        return [
            'system' => [
                'platform' => PHP_OS,
                'platform_description' => $this->getPlatformDescription(),
                'hostname' => gethostname(),
                'ip_address' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
                'timestamp' => date('Y-m-d H:i:s'),
                'timezone' => date_default_timezone_get(),
                'uptime' => $this->getSystemUptime(),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'version_id' => PHP_VERSION_ID,
                'sapi' => PHP_SAPI,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size'),
                'display_errors' => ini_get('display_errors'),
                'error_reporting' => $this->getErrorReportingLevel(),
                'extensions' => $this->getImportantExtensions(),
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
                'port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'gateway_interface' => $_SERVER['GATEWAY_INTERFACE'] ?? 'Unknown',
            ],
            'mcp_server' => [
                'name' => $this->config->getName(),
                'version' => $this->config->getVersion(),
                'description' => $this->config->getDescription(),
                'debug_mode' => $this->config->isDebugMode(),
                'log_level' => $this->config->getLogLevel(),
                'tools_enabled' => count($this->config->getEnabledTools()),
                'security_paths' => $this->config->getAllowedPaths(),
            ],
            'resources' => [
                'memory_usage' => [
                    'current' => $this->formatBytes(memory_get_usage(true)),
                    'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                    'limit' => ini_get('memory_limit'),
                ],
                'disk_space' => $this->getDiskSpaceInfo(),
                'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
                'processes' => $this->getProcessCount(),
            ],
            'security' => [
                'session_status' => session_status(),
                'session_save_path' => session_save_path(),
                'open_basedir' => ini_get('open_basedir') ?: 'Not set',
                'safe_mode' => ini_get('safe_mode'),
                'file_uploads' => ini_get('file_uploads'),
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'allow_url_include' => ini_get('allow_url_include'),
                'disable_functions' => ini_get('disable_functions') ?: 'None',
            ],
        ];
    }

    /**
     * Get human-readable platform description
     */
    private function getPlatformDescription(): string
    {
        $platform = PHP_OS;

        if (str_starts_with($platform, 'WIN')) {
            return 'Windows '.php_uname('r');
        } elseif (str_starts_with($platform, 'Darwin')) {
            return 'macOS '.php_uname('r');
        } elseif (str_starts_with($platform, 'Linux')) {
            $distro = $this->getLinuxDistro();

            return 'Linux '.($distro ?: php_uname('r'));
        }

        return $platform.' '.php_uname('r');
    }

    /**
     * Get Linux distribution information
     */
    private function getLinuxDistro(): ?string
    {
        $files = [
            '/etc/os-release',
            '/etc/lsb-release',
            '/etc/redhat-release',
            '/etc/debian_version',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    if (preg_match(
                        '/(PRETTY_NAME|ID|DISTRIB_ID|DISTRIB_DESCRIPTION)=["\']?([^"\'\n]+)/',
                        $content,
                        $matches
                    )) {
                        return $matches[2];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get system uptime (approximate)
     */
    private function getSystemUptime(): string
    {
        if (PHP_OS === 'WIN') {
            // Try Windows uptime
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('net statistics server | find "Statistics since"');
                if ($uptime) {
                    return trim($uptime);
                }
            }
        } else {
            // Try Linux/Mac uptime
            if (function_exists('shell_exec')) {
                $uptime = shell_exec('uptime');
                if ($uptime) {
                    return trim($uptime);
                }
            }
        }

        return 'Not available';
    }

    /**
     * Get error reporting level as text
     */
    private function getErrorReportingLevel(): string
    {
        $levels = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];

        $errorLevel = error_reporting();
        $result = [];

        foreach ($levels as $level => $name) {
            if ($errorLevel & $level) {
                $result[] = $name;
            }
        }

        return empty($result) ? 'None' : implode(' | ', $result);
    }

    /**
     * Get important PHP extensions
     */
    private function getImportantExtensions(): array
    {
        $important = [
            'curl',
            'json',
            'mbstring',
            'openssl',
            'pdo',
            'pdo_mysql',
            'pdo_sqlite',
            'sqlite3',
            'zip',
            'gd',
            'imagick',
            'redis',
            'memcached',
        ];
        $installed = [];

        foreach ($important as $ext) {
            $installed[$ext] = extension_loaded($ext);
        }

        return $installed;
    }

    /**
     * Get disk space information
     */
    private function getDiskSpaceInfo(): array
    {
        $path = __DIR__;
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;

        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'percentage_used' => $total > 0 ? round(($used / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Get process count (Linux/Unix)
     */
    private function getProcessCount(): ?int
    {
        if (PHP_OS === 'WIN') {
            return null;
        }

        if (function_exists('shell_exec')) {
            $count = shell_exec('ps aux | wc -l');
            if ($count !== null) {
                return (int)trim($count);
            }
        }

        return null;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }

    /**
     * Render a template file with optional variables
     */
    private function renderTemplate(string $templatePath, array $variables = []): string
    {
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        // Extract variables for use in template
        extract($variables);

        // Start output buffering
        ob_start();

        // Include the template
        include $templatePath;

        // Get the buffered content and clean buffer
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Generate login page HTML
     */
    private function getLoginPageContent(): string
    {
        $templatePath = __DIR__ . '/../../templates/views/admin/login.php';
        return $this->renderTemplate($templatePath);
    }

    /**
     * Generate admin dashboard HTML
     */
    private function getDashboardContent(array $user): string
    {
        $templatePath = __DIR__ . '/../../templates/views/admin/dashboard.php';
        return $this->renderTemplate($templatePath, ['user' => $user]);
    }

    /**
     * Generate landing page HTML
     */
    private function getLandingPageContent(): string
    {
        $templatePath = __DIR__ . '/../../templates/views/loading.php';
        return $this->renderTemplate($templatePath);
    }
}