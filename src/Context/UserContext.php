<?php

namespace App\Context;

use App\Models\User;

class UserContext
{
    private ?User $user = null;
    private string $sessionId;
    private string $ipAddress;
    private string $userAgent;
    private array $permissions = [];
    private \DateTime $sessionStart;
    private ?\DateTime $lastActivity = null;
    private bool $isImpersonated = false;
    private ?int $originalUserId = null;

    public function __construct(string $sessionId, string $ipAddress = '', string $userAgent = '')
    {
        $this->sessionId = $sessionId;
        $this->ipAddress = $ipAddress ?: $this->getClientIp();
        $this->userAgent = $userAgent ?: $_SERVER['HTTP_USER_AGENT'] ?? '';
        $this->sessionStart = new \DateTime();
        $this->lastActivity = new \DateTime();
    }

    // User management
    public function setUser(?User $user): void
    {
        $this->user = $user;
        $this->lastActivity = new \DateTime();

        if ($user) {
            $this->refreshPermissions();
        } else {
            $this->permissions = [];
        }
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getUserId(): ?int
    {
        return $this->user?->getId();
    }

    public function getUserEmail(): ?string
    {
        return $this->user?->getEmail();
    }

    public function getUserName(): ?string
    {
        return $this->user?->getName();
    }

    public function getUserRole(): ?string
    {
        return $this->user?->getRole();
    }

    // Authentication state
    public function isAuthenticated(): bool
    {
        return $this->user !== null && $this->user->isActive();
    }

    public function isGuest(): bool
    {
        return $this->user === null;
    }

    public function isActive(): bool
    {
        return $this->user?->isActive() ?? false;
    }

    // Role and permission checking
    public function hasRole(string $role): bool
    {
        return $this->user && $this->user->getRole() === $role;
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('admin');
    }

    public function isRegularUser(): bool
    {
        return $this->hasRole('user');
    }

    public function isViewer(): bool
    {
        return $this->hasRole('viewer');
    }

    public function hasPermission(string $permission): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        // Admin has all permissions
        if ($this->isAdministrator()) {
            return true;
        }

        return in_array($permission, $this->permissions);
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->user?->canAccessAdminPanel() ?? false;
    }

    public function canManageSecrets(): bool
    {
        return $this->user?->canManageSecrets() ?? false;
    }

    public function canShareSecrets(): bool
    {
        return $this->user?->canShareSecrets() ?? false;
    }

    public function canManageUsers(): bool
    {
        return $this->hasPermission('user:write') || $this->hasPermission('user:delete');
    }

    public function canViewLogs(): bool
    {
        return $this->hasPermission('log:read') || $this->hasPermission('log:read_own');
    }

    public function canDeleteLogs(): bool
    {
        return $this->hasPermission('log:delete');
    }

    // Session management
    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getSessionStart(): \DateTime
    {
        return $this->sessionStart;
    }

    public function getLastActivity(): \DateTime
    {
        return $this->lastActivity;
    }

    public function getSessionDuration(): \DateInterval
    {
        return $this->sessionStart->diff(new \DateTime());
    }

    public function getIdleDuration(): \DateInterval
    {
        return $this->lastActivity->diff(new \DateTime());
    }

    public function updateLastActivity(): void
    {
        $this->lastActivity = new \DateTime();
    }

    public function isSessionExpired(int $maxLifetime = 86400): bool
    {
        $idleDuration = $this->getIdleDuration();
        return $idleDuration->s > $maxLifetime;
    }

    // Impersonation (for admin use)
    public function startImpersonation(User $targetUser): void
    {
        if (!$this->isAdministrator()) {
            throw new \RuntimeException('Only administrators can impersonate users');
        }

        $this->originalUserId = $this->getUserId();
        $this->isImpersonated = true;
        $this->setUser($targetUser);
    }

    public function stopImpersonation(): void
    {
        if (!$this->isImpersonated) {
            return;
        }

        $this->isImpersonated = false;
        // Note: We need to reload the original user from database
        $this->originalUserId = null;
    }

    public function isImpersonated(): bool
    {
        return $this->isImpersonated;
    }

    public function getOriginalUserId(): ?int
    {
        return $this->originalUserId;
    }

    // Security and validation
    public function validateSession(): array
    {
        $errors = [];

        if (empty($this->sessionId)) {
            $errors[] = 'Session ID is required';
        }

        if ($this->isAuthenticated() && !$this->isActive()) {
            $errors[] = 'User account is inactive';
        }

        if ($this->isSessionExpired()) {
            $errors[] = 'Session has expired';
        }

        return $errors;
    }

    public function isSecure(): bool
    {
        // Check if connection is HTTPS
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    }

    // Utility methods
    public function getDisplayName(): string
    {
        return $this->user?->getDisplayName() ?? 'Guest';
    }

    public function getInitials(): string
    {
        return $this->user?->getInitials() ?? 'G';
    }

    public function getAvatarUrl(int $size = 40): string
    {
        if ($this->isGuest()) {
            return "https://ui-avatars.com/api/?name=Guest&background=95a5a6&color=fff&size={$size}";
        }

        $name = urlencode($this->getDisplayName());
        $background = $this->getUserRoleColor();
        return "https://ui-avatars.com/api/?name={$name}&background={$background}&color=fff&size={$size}";
    }

    private function getUserRoleColor(): string
    {
        $colors = [
            'admin' => 'e74c3c',
            'user' => '3498db',
            'viewer' => '95a5a6'
        ];

        return $colors[$this->getUserRole()] ?? '95a5a6';
    }

    // Data serialization
    public function toArray(): array
    {
        return [
            'user_id' => $this->getUserId(),
            'user_email' => $this->getUserEmail(),
            'user_name' => $this->getUserName(),
            'user_role' => $this->getUserRole(),
            'is_authenticated' => $this->isAuthenticated(),
            'is_guest' => $this->isGuest(),
            'session_id' => $this->sessionId,
            'ip_address' => $this->ipAddress,
            'session_start' => $this->sessionStart->format('Y-m-d H:i:s'),
            'last_activity' => $this->lastActivity->format('Y-m-d H:i:s'),
            'session_duration' => $this->getSessionDuration()->format('%h:%i:%s'),
            'idle_duration' => $this->getIdleDuration()->format('%h:%i:%s'),
            'is_impersonated' => $this->isImpersonated,
            'permissions' => $this->permissions,
            'display_name' => $this->getDisplayName(),
            'initials' => $this->getInitials(),
            'avatar_url' => $this->getAvatarUrl()
        ];
    }

    public function toSafeArray(): array
    {
        // Excludes sensitive information
        return [
            'user_id' => $this->getUserId(),
            'user_email' => $this->getUserEmail(),
            'user_name' => $this->getUserName(),
            'user_role' => $this->getUserRole(),
            'is_authenticated' => $this->isAuthenticated(),
            'is_guest' => $this->isGuest(),
            'session_start' => $this->sessionStart->format('Y-m-d H:i:s'),
            'last_activity' => $this->lastActivity->format('Y-m-d H:i:s'),
            'session_duration' => $this->getSessionDuration()->format('%h:%i:%s'),
            'idle_duration' => $this->getIdleDuration()->format('%h:%i:%s'),
            'is_impersonated' => $this->isImpersonated,
            'display_name' => $this->getDisplayName(),
            'initials' => $this->getInitials(),
            'avatar_url' => $this->getAvatarUrl()
        ];
    }

    // Private helper methods
    private function refreshPermissions(): void
    {
        if (!$this->user) {
            $this->permissions = [];
            return;
        }

        $this->permissions = $this->getUserPermissions($this->user);
    }

    private function getUserPermissions(User $user): array
    {
        $rolePermissions = [
            'admin' => [
                '*',
                'user:read', 'user:write', 'user:delete',
                'secret:read', 'secret:write', 'secret:delete', 'secret:share',
                'log:read', 'log:delete',
                'system:manage', 'system:monitor'
            ],
            'user' => [
                'secret:read', 'secret:write', 'secret:share',
                'log:read_own',
                'profile:read', 'profile:write',
                'activity:read_own'
            ],
            'viewer' => [
                'secret:read',
                'log:read_own',
                'profile:read',
                'activity:read_own'
            ]
        ];

        return $rolePermissions[$user->getRole()] ?? [];
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

    // Factory methods
    public static function fromRequest(): self
    {
        $sessionId = $_COOKIE['session_id'] ??
                    $_SERVER['HTTP_AUTHORIZATION'] ??
                    session_id() ?:
                    uniqid('guest_', true);

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        return new self($sessionId, $ipAddress, $userAgent);
    }

    public static function createGuest(): self
    {
        return new self('guest_' . uniqid());
    }

    // String representation
    public function __toString(): string
    {
        return $this->getDisplayName() . ' (' . $this->getUserRole() . ')';
    }
}