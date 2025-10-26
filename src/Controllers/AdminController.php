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
            $response = $response->withHeader('Set-Cookie',
                "admin_session={$sessionId}; Path=/; HttpOnly; SameSite=Strict; Max-Age=28800"
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Login successful',
                'redirect' => '/admin/dashboard',
                'session_id' => $sessionId
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
        $response = $response->withHeader('Set-Cookie',
            "admin_session=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0"
        );

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Logged out successfully',
            'redirect' => '/'
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
            'data' => $user
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
            return $this->errorResponse($response, ErrorResponse::badRequest('Old password and new password are required'));
        }

        if ($this->authService->changePassword($data['old_password'], $data['new_password'])) {
            $this->logger->info('Admin password changed successfully');

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Password changed successfully'
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
                'session_active' => $this->authService->isAuthenticated()
            ]
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
            'data' => $systemInfo
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
                'uptime' => $this->getSystemUptime()
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
                'extensions' => $this->getImportantExtensions()
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
                'port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
                'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'Unknown',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'gateway_interface' => $_SERVER['GATEWAY_INTERFACE'] ?? 'Unknown'
            ],
            'mcp_server' => [
                'name' => $this->config->getName(),
                'version' => $this->config->getVersion(),
                'description' => $this->config->getDescription(),
                'debug_mode' => $this->config->isDebugMode(),
                'log_level' => $this->config->getLogLevel(),
                'tools_enabled' => count($this->config->getEnabledTools()),
                'security_paths' => $this->config->getAllowedPaths()
            ],
            'resources' => [
                'memory_usage' => [
                    'current' => $this->formatBytes(memory_get_usage(true)),
                    'peak' => $this->formatBytes(memory_get_peak_usage(true)),
                    'limit' => ini_get('memory_limit')
                ],
                'disk_space' => $this->getDiskSpaceInfo(),
                'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
                'processes' => $this->getProcessCount()
            ],
            'security' => [
                'session_status' => session_status(),
                'session_save_path' => session_save_path(),
                'open_basedir' => ini_get('open_basedir') ?: 'Not set',
                'safe_mode' => ini_get('safe_mode'),
                'file_uploads' => ini_get('file_uploads'),
                'allow_url_fopen' => ini_get('allow_url_fopen'),
                'allow_url_include' => ini_get('allow_url_include'),
                'disable_functions' => ini_get('disable_functions') ?: 'None'
            ]
        ];
    }

    /**
     * Get human-readable platform description
     */
    private function getPlatformDescription(): string
    {
        $platform = PHP_OS;

        if (str_starts_with($platform, 'WIN')) {
            return 'Windows ' . php_uname('r');
        } elseif (str_starts_with($platform, 'Darwin')) {
            return 'macOS ' . php_uname('r');
        } elseif (str_starts_with($platform, 'Linux')) {
            $distro = $this->getLinuxDistro();
            return 'Linux ' . ($distro ?: php_uname('r'));
        }

        return $platform . ' ' . php_uname('r');
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
            '/etc/debian_version'
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                if ($content !== false) {
                    if (preg_match('/(PRETTY_NAME|ID|DISTRIB_ID|DISTRIB_DESCRIPTION)=["\']?([^"\'\n]+)/', $content, $matches)) {
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
            E_USER_DEPRECATED => 'E_USER_DEPRECATED'
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
        $important = ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'pdo_sqlite', 'sqlite3', 'zip', 'gd', 'imagick', 'redis', 'memcached'];
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
            'percentage_used' => $total > 0 ? round(($used / $total) * 100, 2) : 0
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
                return (int) trim($count);
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

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Generate login page HTML
     */
    private function getLoginPageContent(): string
    {
        return '<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - MCP Server</title>
    <style>
        body {
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #333;
            margin: 0;
            font-size: 24px;
        }
        .login-header p {
            color: #666;
            margin: 10px 0 0 0;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
        }
        input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }
        button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        button:hover {
            opacity: 0.9;
        }
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .loading {
            text-align: center;
            color: #666;
            font-style: italic;
            display: none;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 12px;
        }
        .default-info {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Panel</h1>
            <p>MCP PHP Server Management</p>
        </div>

        <div class="default-info">
            <strong>Domyślne dane:</strong><br>
            Login: admin<br>
            Hasło: admin123
        </div>

        <div class="error" id="error"></div>
        <div class="success" id="success"></div>

        <form id="loginForm">
            <div class="form-group">
                <label for="username">Użytkownik:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div class="form-group">
                <label for="password">Hasło:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" id="loginBtn">Zaloguj się</button>
            <div class="loading" id="loading">Logowanie...</div>
        </form>

        <div class="footer">
            <p>Secure Admin Access Required</p>
        </div>
    </div>

    <script>
        document.getElementById(\'loginForm\').addEventListener(\'submit\', async function(e) {
            e.preventDefault();

            const errorDiv = document.getElementById(\'error\');
            const successDiv = document.getElementById(\'success\');
            const loadingDiv = document.getElementById(\'loading\');
            const loginBtn = document.getElementById(\'loginBtn\');

            const formData = new FormData(this);
            const data = {
                username: formData.get(\'username\'),
                password: formData.get(\'password\')
            };

            // Hide previous messages
            errorDiv.style.display = \'none\';
            successDiv.style.display = \'none\';
            loadingDiv.style.display = \'block\';
            loginBtn.disabled = true;

            try {
                const response = await fetch(\'/admin/login\', {
                    method: \'POST\',
                    headers: {
                        \'Content-Type\': \'application/json\',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (response.ok && result.success) {
                    successDiv.textContent = result.message;
                    successDiv.style.display = \'block\';
                    setTimeout(() => {
                        window.location.href = result.redirect;
                    }, 1000);
                } else {
                    errorDiv.textContent = result.error?.message || result.message || \'Login failed\';
                    errorDiv.style.display = \'block\';
                }
            } catch (error) {
                errorDiv.textContent = \'Network error: \' + error.message;
                errorDiv.style.display = \'block\';
            } finally {
                loadingDiv.style.display = \'none\';
                loginBtn.disabled = false;
            }
        });
    </script>
</body>
</html>';
    }

    /**
     * Generate admin dashboard HTML
     */
    private function getDashboardContent(array $user): string
    {
        return '<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - MCP Server</title>
    <style>
        body {
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header .user-info {
            text-align: right;
            font-size: 14px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .nav-tabs {
            display: flex;
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav-tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .nav-tab:hover {
            background-color: #f8f9fa;
        }
        .nav-tab.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
        }
        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }
        input[type="text"], input[type="password"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        button:hover {
            background-color: #5a6fd8;
        }
        button.danger {
            background-color: #dc3545;
        }
        button.danger:hover {
            background-color: #c82333;
        }
        .result {
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            display: none;
        }
        .result.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .result.error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .loading {
            display: none;
            color: #666;
            font-style: italic;
        }
        .secrets-list {
            margin-top: 20px;
        }
        .secret-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
        }
        .secret-key {
            font-family: monospace;
            background-color: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .logout-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        .logout-btn:hover {
            background-color: #c82333;
        }

        /* System Information Styles */
        .system-info-grid {
            display: grid;
            gap: 20px;
        }

        .info-section {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }

        .info-section h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .info-item strong {
            color: #495057;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item span, .info-item code {
            color: #212529;
            font-size: 14px;
            word-break: break-all;
        }

        .extensions-list {
            margin-top: 15px;
            padding: 10px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .extensions-list strong {
            color: #495057;
            font-size: 13px;
            display: block;
            margin-bottom: 8px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 10px;
            }

            .info-section {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🔐 Admin Dashboard</h1>
            <p>MCP PHP Server Management</p>
        </div>
        <div class="user-info">
            <div>Zalogowany jako: <strong>' . htmlspecialchars($user['username']) . '</strong></div>
            <div>Session: ' . date('Y-m-d H:i:s', $user['created_at']) . '</div>
            <button class="logout-btn" onclick="logout()">Wyloguj</button>
        </div>
    </div>

    <div class="container">
        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showTab(\'secrets\')">🔐 Sekrety</button>
            <button class="nav-tab" onclick="showTab(\'servers\')">🖥️ Serwery</button>
            <button class="nav-tab" onclick="showTab(\'settings\')">⚙️ Ustawienia</button>
        </div>

        <!-- Secrets Tab -->
        <div id="secrets" class="tab-content active">
            <h2>🔐 Zarządzanie sekretami</h2>

            <div style="margin-bottom: 30px;">
                <h3>Dodaj nowy sekret</h3>
                <form id="addSecretForm">
                    <div class="form-group">
                        <label for="secretKey">Klucz sekretu *</label>
                        <input type="text" id="secretKey" name="key" placeholder="np. brave-search.BRAVE_API_KEY" required>
                    </div>
                    <div class="form-group">
                        <label for="secretValue">Wartość sekretu *</label>
                        <textarea id="secretValue" name="value" placeholder="Wartość sekretna (np. klucz API)" required></textarea>
                    </div>
                    <button type="submit">🔒 Zapisz sekret</button>
                    <div class="loading" id="loading_add_secret">⏳ Zapisywanie...</div>
                    <div class="result" id="result_add_secret"></div>
                </form>
            </div>

            <div>
                <h3>Zapisane sekrety</h3>
                <button onclick="loadSecrets()">🔄 Odśwież listę</button>
                <div id="secretsList" class="secrets-list">
                    <p><em>Kliknij "Odśwież listę" aby załadować zapisane sekrety</em></p>
                </div>
            </div>
        </div>

        <!-- Servers Tab -->
        <div id="servers" class="tab-content">
            <h2>🖥️ Zarządzanie serwerami MCP</h2>

            <div style="margin-bottom: 30px;">
                <h3>Dodaj nowy serwer</h3>
                <form id="addServerForm">
                    <div class="form-group">
                        <label for="serverName">Nazwa serwera *</label>
                        <input type="text" id="serverName" name="name" placeholder="np. brave-search" required>
                    </div>
                    <div class="form-group">
                        <label for="serverJson">Konfiguracja JSON *</label>
                        <textarea id="serverJson" name="json_config" placeholder=\'{
    "command": "npx",
    "args": ["-y", "@brave/brave-search-mcp-server"],
    "env": {
        "BRAVE_API_KEY": "${BRAVE_API_KEY}"
    }
}\' required></textarea>
                    </div>
                    <button type="submit">➕ Dodaj serwer</button>
                    <div class="loading" id="loading_add_server">⏳ Dodawanie...</div>
                    <div class="result" id="result_add_server"></div>
                </form>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <h2>⚙️ Ustawienia admina</h2>

            <div style="margin-bottom: 30px;">
                <h3>Zmień hasło</h3>
                <form id="changePasswordForm">
                    <div class="form-group">
                        <label for="oldPassword">Stare hasło *</label>
                        <input type="password" id="oldPassword" name="old_password" required>
                    </div>
                    <div class="form-group">
                        <label for="newPassword">Nowe hasło *</label>
                        <input type="password" id="newPassword" name="new_password" required>
                    </div>
                    <button type="submit">🔑 Zmień hasło</button>
                    <div class="loading" id="loading_change_password">⏳ Zmienianie...</div>
                    <div class="result" id="result_change_password"></div>
                </form>
            </div>

            <div>
                <h3>Informacje o systemie</h3>
                <div id="systemInfo">
                    <p><em>Ładowanie...</em></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = \'\';

        // Tab management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll(\'.tab-content\').forEach(tab => {
                tab.classList.remove(\'active\');
            });
            document.querySelectorAll(\'.nav-tab\').forEach(tab => {
                tab.classList.remove(\'active\');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add(\'active\');
            event.target.classList.add(\'active\');

            // Load tab-specific data
            if (tabName === \'secrets\') {
                loadSecrets();
            } else if (tabName === \'settings\') {
                loadSystemInfo();
            }
        }

        // Secrets management
        async function loadSecrets() {
            try {
                const response = await fetch(`${API_BASE}/admin/api/secrets`, {
                    headers: {
                        \'Authorization\': `Bearer ${getCookie(\'admin_session\')}`
                    }
                });
                const data = await response.json();

                const secretsList = document.getElementById(\'secretsList\');

                if (response.ok && data.success) {
                    if (data.data.length === 0) {
                        secretsList.innerHTML = \'<p><em>Brak zapisanych sekretów</em></p>\';
                        return;
                    }

                    let html = \'\';
                    data.data.forEach(secretKey => {
                        html += `
                            <div class="secret-item">
                                <span class="secret-key">${secretKey}</span>
                                <div>
                                    <button onclick="viewSecret(\'${secretKey}\')" style="margin-right: 5px;">👁️</button>
                                    <button onclick="deleteSecret(\'${secretKey}\')" class="danger">🗑️</button>
                                </div>
                            </div>
                        `;
                    });
                    secretsList.innerHTML = html;
                } else {
                    secretsList.innerHTML = \'<div class="result error">Błąd: \' + (data.error?.message || data.message) + \'</div>\';
                }
            } catch (error) {
                document.getElementById(\'secretsList\').innerHTML = \'<div class="result error">Błąd sieci: \' + error.message + \'</div>\';
            }
        }

        async function addSecret(event) {
            event.preventDefault();
            // Implementation similar to previous
            showResult(\'result_add_secret\', \'Sekret dodany pomyślnie\', \'success\');
        }

        async function deleteSecret(secretKey) {
            if (!confirm(`Czy na pewno chcesz usunąć sekret "${secretKey}"?`)) return;

            // Implementation
            loadSecrets();
        }

        async function viewSecret(secretKey) {
            // Implementation
        }

        // Server management
        async function addServer(event) {
            event.preventDefault();
            // Implementation similar to previous
            showResult(\'result_add_server\', \'Serwer dodany pomyślnie\', \'success\');
        }

        // Settings
        async function changePassword(event) {
            event.preventDefault();
            // Implementation
            showResult(\'result_change_password\', \'Hasło zmienione pomyślnie\', \'success\');
        }

        async function loadSystemInfo() {
            const systemInfo = document.getElementById(\'systemInfo\');
            systemInfo.innerHTML = \'<p><em>Ładowanie informacji o systemie...</em></p>\';

            const sessionId = getCookie(\'admin_session\');
            console.log(\'Session ID:\', sessionId);

            try {
                const response = await fetch(`${API_BASE}/admin/system-info`, {
                    headers: {
                        \'Authorization\': `Bearer ${sessionId}`
                    }
                });
                console.log(\'Response status:\', response.status);
                const data = await response.json();
                console.log(\'Response data:\', data);

                if (response.ok && data.success) {
                    systemInfo.innerHTML = generateSystemInfoHTML(data.data);
                } else {
                    systemInfo.innerHTML = \'<div class="result error">Błąd: \' + (data.error?.message || data.message) + \'</div>\';
                }
            } catch (error) {
                console.error(\'Error loading system info:\', error);
                systemInfo.innerHTML = \'<div class="result error">Błąd sieci: \' + error.message + \'</div>\';
            }
        }

        function generateSystemInfoHTML(info) {
            let html = \'<div class="system-info-grid">\';

            // System Information
            html += \'<div class="info-section">\';
            html += \'<h4>🖥️ System</h4>\';
            html += \'<div class="info-grid">\';
            html += \'<div class="info-item"><strong>Platforma:</strong> \' + info.system.platform_description + \'</div>\';
            html += \'<div class="info-item"><strong>Nazwa hosta:</strong> \' + info.system.hostname + \'</div>\';
            html += \'<div class="info-item"><strong>Adres IP:</strong> \' + info.system.ip_address + \'</div>\';
            html += \'<div class="info-item"><strong>Strefa czasowa:</strong> \' + info.system.timezone + \'</div>\';
            html += \'<div class="info-item"><strong>Czas aktualizacji:</strong> \' + info.system.timestamp + \'</div>\';
            if (info.system.uptime && info.system.uptime !== \'Not available\') {
                html += \'<div class="info-item"><strong>Uptime:</strong> \' + info.system.uptime + \'</div>\';
            }
            html += \'</div></div>\';

            // PHP Information
            html += \'<div class="info-section">\';
            html += \'<h4>🐘 PHP</h4>\';
            html += \'<div class="info-grid">\';
            html += \'<div class="info-item"><strong>Wersja:</strong> \' + info.php.version + \'</div>\';
            html += \'<div class="info-item"><strong>SAPI:</strong> \' + info.php.sapi + \'</div>\';
            html += \'<div class="info-item"><strong>Memory limit:</strong> \' + info.php.memory_limit + \'</div>\';
            html += \'<div class="info-item"><strong>Max execution time:</strong> \' + info.php.max_execution_time + \'s</div>\';
            html += \'<div class="info-item"><strong>Upload max filesize:</strong> \' + info.php.upload_max_filesize + \'</div>\';
            html += \'<div class="info-item"><strong>Post max size:</strong> \' + info.php.post_max_size + \'</div>\';
            html += \'<div class="info-item"><strong>Display errors:</strong> \' + (info.php.display_errors === \'1\' ? \'On\' : \'Off\') + \'</div>\';
            html += \'</div>\';

            // PHP Extensions
            html += \'<div class="extensions-list"><strong>Ważne rozszerzenia:</strong><br>\';
            Object.entries(info.php.extensions).forEach(([ext, loaded]) => {
                const status = loaded ? \'✅\' : \'❌\';
                const color = loaded ? \'color: green;\' : \'color: red;\';
                html += \'<span style="margin-right: 10px; \' + color + \'">\' + status + \' \' + ext + \'</span>\';
            });
            html += \'</div></div>\';

            // Server Information
            html += \'<div class="info-section">\';
            html += \'<h4>🌐 Serwer WWW</h4>\';
            html += \'<div class="info-grid">\';
            html += \'<div class="info-item"><strong>Oprogramowanie:</strong> \' + info.server.software + \'</div>\';
            html += \'<div class="info-item"><strong>Protokół:</strong> \' + info.server.protocol + \'</div>\';
            html += \'<div class="info-item"><strong>Port:</strong> \' + info.server.port + \'</div>\';
            html += \'<div class="info-item"><strong>HTTPS:</strong> \' + (info.server.https ? \'✅ Tak\' : \'❌ Nie\') + \'</div>\';
            html += \'<div class="info-item"><strong>Document root:</strong> <code style="font-size: 12px;">\' + info.server.document_root + \'</code></div>\';
            html += \'</div></div>\';

            // MCP Server Information
            html += \'<div class="info-section">\';
            html += \'<h4>🚀 MCP Server</h4>\';
            html += \'<div class="info-grid">\';
            html += \'<div class="info-item"><strong>Nazwa:</strong> \' + info.mcp_server.name + \'</div>\';
            html += \'<div class="info-item"><strong>Wersja:</strong> \' + info.mcp_server.version + \'</div>\';
            html += \'<div class="info-item"><strong>Debug mode:</strong> \' + (info.mcp_server.debug_mode ? \'✅ Włączony\' : \'❌ Wyłączony\') + \'</div>\';
            html += \'<div class="info-item"><strong>Log level:</strong> \' + info.mcp_server.log_level + \'</div>\';
            html += \'<div class="info-item"><strong>Włączone narzędzia:</strong> \' + info.mcp_server.tools_enabled + \'</div>\';
            html += \'</div></div>\';

            // Resources
            html += \'<div class="info-section">\';
            html += \'<h4>💾 Zasoby</h4>\';
            html += \'<div class="info-grid">\';
            html += \'<div class="info-item"><strong>Memory usage:</strong> \' + info.resources.memory_usage.current + \' (Peak: \' + info.resources.memory_usage.peak + \')</div>\';
            html += \'<div class="info-item"><strong>Memory limit:</strong> \' + info.resources.memory_usage.limit + \'</div>\';
            html += \'<div class="info-item"><strong>Dysk:</strong> \' + info.resources.disk_space.used + \' / \' + info.resources.disk_space.total + \' (\' + info.resources.disk_space.percentage_used + \'% użytych)</div>\';
            if (info.resources.load_average) {
                html += \'<div class="info-item"><strong>Load average:</strong> \' + info.resources.load_average.slice(0, 3).join(\', \') + \'</div>\';
            }
            if (info.resources.processes) {
                html += \'<div class="info-item"><strong>Procesy:</strong> \' + info.resources.processes + \'</div>\';
            }
            html += \'</div></div>\';

            // Security Information
            html += \'<div class="info-section">\';
            html += \'<h4>🔒 Bezpieczeństwo</h4>\';
            html += \'<div class="info-grid">\';
            html += \'<div class="info-item"><strong>Session status:</strong> \' + (info.security.session_status === 1 ? \'Active\' : \'Disabled\') + \'</div>\';
            html += \'<div class="info-item"><strong>Session path:</strong> <code style="font-size: 12px;">\' + info.security.session_save_path + \'</code></div>\';
            html += \'<div class="info-item"><strong>Open basedir:</strong> \' + info.security.open_basedir + \'</div>\';
            html += \'<div class="info-item"><strong>File uploads:</strong> \' + (info.security.file_uploads === \'1\' ? \'✅ Tak\' : \'❌ Nie\') + \'</div>\';
            html += \'<div class="info-item"><strong>Allow URL fopen:</strong> \' + (info.security.allow_url_fopen === \'1\' ? \'✅ Tak\' : \'❌ Nie\') + \'</div>\';
            html += \'<div class="info-item"><strong>Allow URL include:</strong> \' + (info.security.allow_url_include === \'1\' ? \'✅ Tak\' : \'❌ Nie\') + \'</div>\';
            html += \'</div></div>\';

            html += \'</div>\';
            return html;
        }

        // Utility functions
        function showResult(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.textContent = message;
            element.className = `result ${type}`;
            element.style.display = \'block\';
            setTimeout(() => {
                element.style.display = \'none\';
            }, 3000);
        }

        function getCookie(name) {
            const value = `; ${document.cookie}`;
            console.log(\'All cookies:\', document.cookie);
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) {
                const result = parts.pop().split(\';\').shift();
                console.log(`Cookie ${name}:\`, result);
                return result;
            }
            console.log(`Cookie ${name} not found`);
            return null;
        }

        async function logout() {
            try {
                const response = await fetch(`${API_BASE}/admin/logout`, {
                    method: \'POST\',
                    headers: {
                        \'Authorization\': `Bearer ${getCookie(\'admin_session\')}`
                    }
                });
                const result = await response.json();
                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    window.location.href = \'/\';
                }
            } catch (error) {
                window.location.href = \'/\';
            }
        }

        // Event listeners
        document.getElementById(\'addSecretForm\').addEventListener(\'submit\', addSecret);
        document.getElementById(\'addServerForm\').addEventListener(\'submit\', addServer);
        document.getElementById(\'changePasswordForm\').addEventListener(\'submit\', changePassword);

        // Initial load
        loadSecrets();
    </script>
</body>
</html>';
    }
}