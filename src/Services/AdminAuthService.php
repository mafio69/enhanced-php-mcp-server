<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use RuntimeException;

class AdminAuthService
{
    private LoggerInterface $logger;
    private string $adminUsername;
    private string $adminPasswordHash;
    private string $sessionPath;

    public function __construct(LoggerInterface $logger, ?string $adminUsername = null, ?string $adminPassword = null)
    {
        $this->logger = $logger;
        $this->adminUsername = $adminUsername ?? $_ENV['ADMIN_USERNAME'] ?? 'admin';
        $this->adminPasswordHash = $this->getAdminPasswordHash($adminPassword);
        $this->sessionPath = __DIR__.'/../../storage/sessions';

        $this->initializeSessions();
    }

    /**
     * Initialize session storage directory
     */
    private function initializeSessions(): void
    {
        if (!is_dir($this->sessionPath)) {
            if (!mkdir($this->sessionPath, 0700, true)) {
                throw new RuntimeException("Cannot create sessions directory: {$this->sessionPath}");
            }
        }
        chmod($this->sessionPath, 0700);
    }

    /**
     * Get admin password hash from environment or generate default
     */
    private function getAdminPasswordHash(?string $adminPassword): string
    {
        if ($adminPassword) {
            return password_hash($adminPassword, PASSWORD_DEFAULT);
        }

        // Try to get from environment
        $password = $_ENV['ADMIN_PASSWORD'] ?? null;
        if ($password) {
            return password_hash($password, PASSWORD_DEFAULT);
        }

        // Default password - change in production!
        return password_hash('admin123', PASSWORD_DEFAULT);
    }

    /**
     * Authenticate admin user
     */
    public function authenticate(string $username, string $password): bool
    {
        if ($username !== $this->adminUsername) {
            $this->logger->warning('Admin login attempt with invalid username', ['username' => $username]);

            return false;
        }

        if (!password_verify($password, $this->adminPasswordHash)) {
            $this->logger->warning('Admin login attempt with invalid password', ['username' => $username]);

            return false;
        }

        $this->logger->info('Admin login successful', ['username' => $username]);

        return true;
    }

    /**
     * Create admin session
     */
    public function createSession(string $username): string
    {
        $sessionId = $this->generateSecureToken();
        $sessionData = [
            'username' => $username,
            'created_at' => time(),
            'expires_at' => time() + (8 * 60 * 60), // 8 hours
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];

        $sessionFile = $this->sessionPath.'/'.$sessionId.'.sess';
        if (file_put_contents($sessionFile, json_encode($sessionData)) === false) {
            throw new RuntimeException("Failed to create session file");
        }

        chmod($sessionFile, 0600);
        $this->logger->info('Admin session created', ['session_id' => substr($sessionId, 0, 8).'...']);

        return $sessionId;
    }

    /**
     * Validate admin session
     */
    public function validateSession(string $sessionId): ?array
    {
        $sessionFile = $this->sessionPath.'/'.$sessionId.'.sess';

        if (!file_exists($sessionFile)) {
            return null;
        }

        $sessionData = json_decode(file_get_contents($sessionFile), true);
        if (!$sessionData) {
            $this->deleteSession($sessionId);

            return null;
        }

        // Check expiration
        if (time() > $sessionData['expires_at']) {
            $this->deleteSession($sessionId);

            return null;
        }

        // Update session expiry
        $sessionData['expires_at'] = time() + (8 * 60 * 60);
        file_put_contents($sessionFile, json_encode($sessionData));

        return $sessionData;
    }

    /**
     * Delete admin session
     */
    public function deleteSession(string $sessionId): void
    {
        $sessionFile = $this->sessionPath.'/'.$sessionId.'.sess';
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
            $this->logger->info('Admin session deleted', ['session_id' => substr($sessionId, 0, 8).'...']);
        }
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $count = 0;
        $currentTime = time();

        foreach (glob($this->sessionPath.'/*.sess') as $sessionFile) {
            $sessionData = json_decode(file_get_contents($sessionFile), true);
            if ($sessionData && $currentTime > $sessionData['expires_at']) {
                unlink($sessionFile);
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Cleaned up expired sessions', ['count' => $count]);
        }

        return $count;
    }

    /**
     * Generate secure token
     */
    private function generateSecureToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Check if admin credentials are set
     */
    public function isConfigured(): bool
    {
        return !empty($this->adminUsername) && !empty($this->adminPasswordHash);
    }

    /**
     * Get admin username (for display)
     */
    public function getAdminUsername(): string
    {
        return $this->adminUsername;
    }

    /**
     * Change admin password
     */
    public function changePassword(string $oldPassword, string $newPassword): bool
    {
        if (!password_verify($oldPassword, $this->adminPasswordHash)) {
            return false;
        }

        $this->adminPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->logger->info('Admin password changed');

        return true;
    }

    /**
     * Get session from request headers
     */
    public function getSessionFromRequest(): ?string
    {
        // Try Authorization header first
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        // Try cookie
        return $_COOKIE['admin_session'] ?? null;
    }

    /**
     * Check if request is from authenticated admin
     */
    public function isAuthenticated(): bool
    {
        $sessionId = $this->getSessionFromRequest();
        if (!$sessionId) {
            return false;
        }

        return $this->validateSession($sessionId) !== null;
    }

    /**
     * Get current admin user data
     */
    public function getCurrentUser(): ?array
    {
        $sessionId = $this->getSessionFromRequest();
        if (!$sessionId) {
            return null;
        }

        return $this->validateSession($sessionId);
    }
}