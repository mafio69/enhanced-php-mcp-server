<?php

namespace App\Services;

use App\Context\UserContext;
use App\Models\User;
use App\Services\UserAwareLogger;
use Exception;

class AuthenticationService
{
    private array $sessionStorage = [];
    private array $loginAttempts = [];
    private array $lockedAccounts = [];
    private UserAwareLogger $logger;
    private array $config;
    private ?SecretAutoLoader $secretAutoLoader = null;

    public function __construct(UserAwareLogger $logger, array $config = [])
    {
        $this->logger = $logger;
        $this->config = array_merge([
            'session_timeout' => 86400, // 24 hours
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'remember_me_duration' => 2592000, // 30 days
            'max_concurrent_sessions' => 5,
            'password_min_length' => 8,
            'require_strong_password' => true,
            'auto_load_secrets' => true // Enable automatic secret loading
        ], $config);
    }

    /**
     * Set the SecretAutoLoader for automatic secret management
     */
    public function setSecretAutoLoader(SecretAutoLoader $secretAutoLoader): void
    {
        $this->secretAutoLoader = $secretAutoLoader;
    }

    public function login(string $email, string $password, bool $rememberMe = false): ?string
    {
        $email = strtolower(trim($email));
        $ipAddress = $this->getClientIp();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check if IP is rate limited
        if ($this->isIpRateLimited($ipAddress)) {
            $this->logger->logSecurityEvent('login_blocked_rate_limit', [
                'email' => $email,
                'ip_address' => $ipAddress,
                'reason' => 'rate_limit_exceeded'
            ]);

            return null;
        }

        // Check if account is locked
        if ($this->isAccountLocked($email)) {
            $this->logger->logSecurityEvent('login_blocked_account_locked', [
                'email' => $email,
                'ip_address' => $ipAddress,
                'reason' => 'account_locked'
            ]);

            return null;
        }

        try {
            // In a real implementation, we would fetch from database
            $user = $this->findUserByEmail($email);

            if (!$user || !$user->verifyPassword($password)) {
                $this->recordFailedLogin($email, $ipAddress);
                return null;
            }

            if (!$user->isActive()) {
                $this->logger->logAuthenticationEvent('login_failed_inactive', [
                    'email' => $email,
                    'user_id' => $user->getId(),
                    'ip_address' => $ipAddress
                ]);

                return null;
            }

            // Check concurrent sessions limit
            if ($this->getUserActiveSessionCount($user->getId()) >= $this->config['max_concurrent_sessions']) {
                $this->logger->logSecurityEvent('login_blocked_too_many_sessions', [
                    'user_id' => $user->getId(),
                    'email' => $email,
                    'ip_address' => $ipAddress,
                    'session_count' => $this->getUserActiveSessionCount($user->getId())
                ]);

                return null;
            }

            // Generate session ID
            $sessionId = $this->generateSessionId();

            // Store session
            $this->sessionStorage[$sessionId] = [
                'user_id' => $user->getId(),
                'email' => $email,
                'created_at' => time(),
                'last_activity' => time(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'remember_me' => $rememberMe,
                'expires_at' => $rememberMe ?
                    time() + $this->config['remember_me_duration'] :
                    time() + $this->config['session_timeout']
            ];

            // Update user last login
            $user->updateLastLogin();
            $this->saveUser($user);

            // Clear failed login attempts
            $this->clearFailedLoginAttempts($email);

            // Log successful login
            $this->logger->logAuthenticationEvent('login_success', [
                'user_id' => $user->getId(),
                'email' => $email,
                'session_id' => $sessionId,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'remember_me' => $rememberMe
            ]);

            // Auto-load user secrets if enabled and auto-loader is available
            if ($this->config['auto_load_secrets'] && $this->secretAutoLoader) {
                try {
                    $secretLoadResult = $this->secretAutoLoader->loadUserSecrets();
                    $this->logger->logAuthenticationEvent('auto_load_secrets', [
                        'user_id' => $user->getId(),
                        'loaded_count' => $secretLoadResult['loaded_count'],
                        'error_count' => $secretLoadResult['error_count']
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Auto-load secrets failed', [
                        'user_id' => $user->getId(),
                        'error' => $e->getMessage()
                    ]);
                    // Don't fail login if secret loading fails
                }
            }

            return $sessionId;

        } catch (Exception $e) {
            $this->logger->error('Login error', [
                'email' => $email,
                'error' => $e->getMessage(),
                'ip_address' => $ipAddress
            ]);

            return null;
        }
    }

    public function logout(string $sessionId): bool
    {
        if (!isset($this->sessionStorage[$sessionId])) {
            return false;
        }

        $sessionData = $this->sessionStorage[$sessionId];

        // Clear loaded secrets if auto-loader is available
        if ($this->secretAutoLoader && $this->config['auto_load_secrets']) {
            try {
                // Set up user context for auto-loader
                $userContext = new UserContext(
                    $sessionId,
                    $sessionData['ip_address'],
                    $sessionData['user_agent']
                );
                $user = $this->findUserById($sessionData['user_id']);
                if ($user) {
                    $userContext->setUser($user);
                    $this->secretAutoLoader->clearLoadedSecrets();
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to clear secrets on logout', [
                    'user_id' => $sessionData['user_id'],
                    'session_id' => $sessionId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        unset($this->sessionStorage[$sessionId]);

        $this->logger->logAuthenticationEvent('logout', [
            'user_id' => $sessionData['user_id'],
            'email' => $sessionData['email'],
            'session_id' => $sessionId,
            'session_duration' => time() - $sessionData['created_at']
        ]);

        return true;
    }

    public function authenticateSession(string $sessionId): ?User
    {
        if (!isset($this->sessionStorage[$sessionId])) {
            return null;
        }

        $sessionData = $this->sessionStorage[$sessionId];

        // Check if session has expired
        if (time() > $sessionData['expires_at']) {
            unset($this->sessionStorage[$sessionId]);
            $this->logger->logAuthenticationEvent('session_expired', [
                'session_id' => $sessionId,
                'user_id' => $sessionData['user_id']
            ]);
            return null;
        }

        // Update last activity
        $this->sessionStorage[$sessionId]['last_activity'] = time();

        try {
            $user = $this->findUserById($sessionData['user_id']);

            if (!$user || !$user->isActive()) {
                unset($this->sessionStorage[$sessionId]);
                return null;
            }

            return $user;
        } catch (Exception $e) {
            $this->logger->error('Session authentication error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            unset($this->sessionStorage[$sessionId]);
            return null;
        }
    }

    public function createUserContext(string $sessionId): UserContext
    {
        $sessionData = $this->sessionStorage[$sessionId] ?? null;

        if (!$sessionData) {
            return UserContext::createGuest();
        }

        $user = $this->authenticateSession($sessionId);

        $userContext = new UserContext(
            $sessionId,
            $sessionData['ip_address'],
            $sessionData['user_agent']
        );

        if ($user) {
            $userContext->setUser($user);
        }

        return $userContext;
    }

    public function register(string $email, string $password, string $name = '', string $role = 'user'): array
    {
        $errors = [];

        // Validate email
        if (!User::validateEmail($email)) {
            $errors[] = 'Invalid email address';
        }

        // Check if email already exists
        if ($this->findUserByEmail($email)) {
            $errors[] = 'Email address already registered';
        }

        // Validate password
        if ($this->config['require_strong_password']) {
            $passwordErrors = User::validatePassword($password);
            if (!empty($passwordErrors)) {
                $errors = array_merge($errors, $passwordErrors);
            }
        } elseif (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $user = new User($email, $password, $name, $role);
            $this->saveUser($user);

            $this->logger->logAuthenticationEvent('user_registered', [
                'user_id' => $user->getId(),
                'email' => $email,
                'name' => $name,
                'role' => $role
            ]);

            return ['success' => true, 'user' => $user->toArray()];

        } catch (Exception $e) {
            $this->logger->error('Registration error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'errors' => ['Registration failed. Please try again.']];
        }
    }

    public function requestPasswordReset(string $email): bool
    {
        $user = $this->findUserByEmail($email);

        if (!$user) {
            // Don't reveal if email exists or not
            return true;
        }

        try {
            $resetToken = $user->generateResetToken();
            $this->saveUser($user);

            $this->logger->logAuthenticationEvent('password_reset_requested', [
                'user_id' => $user->getId(),
                'email' => $email,
                'reset_token' => $resetToken
            ]);

            // In a real implementation, send email here
            // $this->sendPasswordResetEmail($email, $resetToken);

            return true;

        } catch (Exception $e) {
            $this->logger->error('Password reset request error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function resetPassword(string $token, string $newPassword): array
    {
        $errors = [];

        // Validate password
        if ($this->config['require_strong_password']) {
            $passwordErrors = User::validatePassword($newPassword);
            if (!empty($passwordErrors)) {
                $errors = array_merge($errors, $passwordErrors);
            }
        } elseif (strlen($newPassword) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $user = $this->findUserByResetToken($token);

            if (!$user || !$user->isResetTokenValid($token)) {
                return ['success' => false, 'errors' => ['Invalid or expired reset token']];
            }

            $user->changePassword($newPassword);
            $this->saveUser($user);

            // Invalidate all user sessions
            $this->invalidateUserSessions($user->getId());

            $this->logger->logAuthenticationEvent('password_reset_completed', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error('Password reset error', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'errors' => ['Password reset failed. Please try again.']];
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $errors = [];

        try {
            $user = $this->findUserById($userId);

            if (!$user) {
                return ['success' => false, 'errors' => ['User not found']];
            }

            if (!$user->verifyPassword($currentPassword)) {
                $this->logger->logSecurityEvent('password_change_failed', [
                    'user_id' => $userId,
                    'reason' => 'invalid_current_password'
                ]);
                return ['success' => false, 'errors' => ['Current password is incorrect']];
            }

            // Validate new password
            if ($this->config['require_strong_password']) {
                $passwordErrors = User::validatePassword($newPassword);
                if (!empty($passwordErrors)) {
                    $errors = array_merge($errors, $passwordErrors);
                }
            } elseif (strlen($newPassword) < $this->config['password_min_length']) {
                $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
            }

            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            $user->changePassword($newPassword);
            $this->saveUser($user);

            // Invalidate all user sessions except current
            $this->invalidateUserSessions($userId);

            $this->logger->logAuthenticationEvent('password_changed', [
                'user_id' => $userId,
                'email' => $user->getEmail()
            ]);

            return ['success' => true];

        } catch (Exception $e) {
            $this->logger->error('Password change error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'errors' => ['Password change failed. Please try again.']];
        }
    }

    // Session management
    public function getActiveSessions(int $userId): array
    {
        $sessions = [];

        foreach ($this->sessionStorage as $sessionId => $sessionData) {
            if ($sessionData['user_id'] === $userId) {
                $sessions[$sessionId] = [
                    'session_id' => $sessionId,
                    'created_at' => $sessionData['created_at'],
                    'last_activity' => $sessionData['last_activity'],
                    'ip_address' => $sessionData['ip_address'],
                    'user_agent' => $sessionData['user_agent'],
                    'expires_at' => $sessionData['expires_at'],
                    'remember_me' => $sessionData['remember_me']
                ];
            }
        }

        return $sessions;
    }

    public function revokeSession(string $sessionId, int $requestingUserId): bool
    {
        if (!isset($this->sessionStorage[$sessionId])) {
            return false;
        }

        $sessionData = $this->sessionStorage[$sessionId];

        // Users can only revoke their own sessions, admins can revoke any
        if ($sessionData['user_id'] !== $requestingUserId && !$this->isAdmin($requestingUserId)) {
            return false;
        }

        unset($this->sessionStorage[$sessionId]);

        $this->logger->logAuthenticationEvent('session_revoked', [
            'user_id' => $sessionData['user_id'],
            'session_id' => $sessionId,
            'revoked_by' => $requestingUserId
        ]);

        return true;
    }

    public function revokeAllSessions(int $userId, int $requestingUserId): int
    {
        $count = 0;

        foreach ($this->sessionStorage as $sessionId => $sessionData) {
            if ($sessionData['user_id'] === $userId) {
                // Users can only revoke their own sessions, admins can revoke any
                if ($userId === $requestingUserId || $this->isAdmin($requestingUserId)) {
                    unset($this->sessionStorage[$sessionId]);
                    $count++;
                }
            }
        }

        if ($count > 0) {
            $this->logger->logAuthenticationEvent('all_sessions_revoked', [
                'user_id' => $userId,
                'revoked_by' => $requestingUserId,
                'sessions_count' => $count
            ]);
        }

        return $count;
    }

    // Private helper methods
    private function recordFailedLogin(string $email, string $ipAddress): void
    {
        $key = $email . ':' . $ipAddress;

        if (!isset($this->loginAttempts[$key])) {
            $this->loginAttempts[$key] = [];
        }

        $this->loginAttempts[$key][] = time();

        // Keep only recent attempts (last hour)
        $this->loginAttempts[$key] = array_filter(
            $this->loginAttempts[$key],
            fn($time) => $time > (time() - 3600)
        );

        // Lock account if too many attempts
        if (count($this->loginAttempts[$key]) >= $this->config['max_login_attempts']) {
            $this->lockedAccounts[$email] = time() + $this->config['lockout_duration'];
        }

        $this->logger->logSecurityEvent('login_failed', [
            'email' => $email,
            'ip_address' => $ipAddress,
            'attempts' => count($this->loginAttempts[$key])
        ]);
    }

    private function clearFailedLoginAttempts(string $email): void
    {
        unset($this->lockedAccounts[$email]);

        // Clear attempts for this email from all IPs
        foreach ($this->loginAttempts as $key => $attempts) {
            if (strpos($key, $email . ':') === 0) {
                unset($this->loginAttempts[$key]);
            }
        }
    }

    private function isAccountLocked(string $email): bool
    {
        return isset($this->lockedAccounts[$email]) &&
               $this->lockedAccounts[$email] > time();
    }

    private function isIpRateLimited(string $ipAddress): bool
    {
        $recentAttempts = 0;
        $timeThreshold = time() - 300; // Last 5 minutes

        foreach ($this->loginAttempts as $key => $attempts) {
            if (strpos($key, ':' . $ipAddress) !== false) {
                $recentAttempts += count(array_filter(
                    $attempts,
                    fn($time) => $time > $timeThreshold
                ));
            }
        }

        return $recentAttempts >= 20; // Max 20 attempts per 5 minutes
    }

    private function getUserActiveSessionCount(int $userId): int
    {
        $count = 0;
        foreach ($this->sessionStorage as $sessionData) {
            if ($sessionData['user_id'] === $userId && time() < $sessionData['expires_at']) {
                $count++;
            }
        }
        return $count;
    }

    private function invalidateUserSessions(int $userId): void
    {
        foreach ($this->sessionStorage as $sessionId => $sessionData) {
            if ($sessionData['user_id'] === $userId) {
                unset($this->sessionStorage[$sessionId]);
            }
        }
    }

    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function isAdmin(int $userId): bool
    {
        $user = $this->findUserById($userId);
        return $user && $user->getRole() === 'admin';
    }

    // Database simulation methods (replace with actual database implementation)
    private function findUserByEmail(string $email): ?User
    {
        // In a real implementation, query database
        // For now, return null
        return null;
    }

    private function findUserById(int $id): ?User
    {
        // In a real implementation, query database
        // For now, return null
        return null;
    }

    private function findUserByResetToken(string $token): ?User
    {
        // In a real implementation, query database
        // For now, return null
        return null;
    }

    private function saveUser(User $user): bool
    {
        // In a real implementation, save to database
        // For now, return true
        return true;
    }

    // Cleanup methods
    public function cleanupExpiredSessions(): int
    {
        $count = 0;
        $currentTime = time();

        foreach ($this->sessionStorage as $sessionId => $sessionData) {
            if ($currentTime > $sessionData['expires_at']) {
                unset($this->sessionStorage[$sessionId]);
                $count++;
            }
        }

        if ($count > 0) {
            $this->logger->info('Cleaned up expired sessions', ['count' => $count]);
        }

        return $count;
    }

    public function cleanupFailedAttempts(): int
    {
        $count = 0;
        $timeThreshold = time() - 3600; // 1 hour ago

        foreach ($this->loginAttempts as $key => $attempts) {
            $this->loginAttempts[$key] = array_filter(
                $attempts,
                fn($time) => $time > $timeThreshold
            );

            if (empty($this->loginAttempts[$key])) {
                unset($this->loginAttempts[$key]);
                $count++;
            }
        }

        // Cleanup expired locks
        foreach ($this->lockedAccounts as $email => $lockTime) {
            if ($lockTime < time()) {
                unset($this->lockedAccounts[$email]);
                $count++;
            }
        }

        return $count;
    }

    // Statistics
    public function getAuthenticationStats(): array
    {
        $totalSessions = count($this->sessionStorage);
        $activeSessions = count(array_filter(
            $this->sessionStorage,
            fn($session) => time() < $session['expires_at']
        ));

        $lockedAccounts = count(array_filter(
            $this->lockedAccounts,
            fn($lockTime) => $lockTime > time()
        ));

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'expired_sessions' => $totalSessions - $activeSessions,
            'locked_accounts' => $lockedAccounts,
            'failed_login_attempts' => array_sum(array_map('count', $this->loginAttempts))
        ];
    }
}