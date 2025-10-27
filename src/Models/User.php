<?php

namespace App\Models;

use JsonSerializable;

class User implements JsonSerializable
{
    private ?int $id = null;
    private string $email;
    private string $passwordHash;
    private string $name;
    private string $role;
    private bool $isActive;
    private \DateTime $createdAt;
    private ?\DateTime $lastLoginAt = null;
    private array $preferences;
    private string $resetToken;
    private ?\DateTime $resetTokenExpires = null;

    public function __construct(
        string $email,
        string $password,
        string $name = '',
        string $role = 'user'
    ) {
        $this->email = strtolower(trim($email));
        $this->passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        $this->name = trim($name);
        $this->role = in_array($role, ['admin', 'user', 'viewer']) ? $role : 'user';
        $this->isActive = true;
        $this->createdAt = new \DateTime();
        $this->preferences = [];
        $this->resetToken = '';
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getName(): string
    {
        return $this->name ?: $this->email;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        if (in_array($role, ['admin', 'user', 'viewer'])) {
            $this->role = $role;
        }
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getLastLoginAt(): ?\DateTime
    {
        return $this->lastLoginAt;
    }

    public function getPreferences(): array
    {
        return $this->preferences;
    }

    public function setPreferences(array $preferences): void
    {
        $this->preferences = $preferences;
    }

    public function getResetToken(): string
    {
        return $this->resetToken;
    }

    public function getResetTokenExpires(): ?\DateTime
    {
        return $this->resetTokenExpires;
    }

    // Authentication methods
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function changePassword(string $newPassword): void
    {
        $this->passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
        $this->clearResetToken();
    }

    public function updateLastLogin(): void
    {
        $this->lastLoginAt = new \DateTime();
    }

    // Password reset methods
    public function generateResetToken(): string
    {
        $this->resetToken = bin2hex(random_bytes(32));
        $this->resetTokenExpires = (new \DateTime())->add(new \DateInterval('PT1H')); // 1 hour
        return $this->resetToken;
    }

    public function clearResetToken(): void
    {
        $this->resetToken = '';
        $this->resetTokenExpires = null;
    }

    public function isResetTokenValid(string $token): bool
    {
        return $this->resetToken === $token &&
               $this->resetTokenExpires !== null &&
               $this->resetTokenExpires > new \DateTime();
    }

    // Permission methods
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getRolePermissions();
        return in_array($permission, $permissions[$this->role] ?? []);
    }

    public function canAccessAdminPanel(): bool
    {
        return $this->role === 'admin';
    }

    public function canManageSecrets(): bool
    {
        return in_array($this->role, ['admin', 'user']);
    }

    public function canShareSecrets(): bool
    {
        return in_array($this->role, ['admin', 'user']);
    }

    private function getRolePermissions(): array
    {
        return [
            'admin' => [
                '*',
                'user:read', 'user:write', 'user:delete',
                'secret:read', 'secret:write', 'secret:delete', 'secret:share',
                'log:read', 'log:delete',
                'system:manage'
            ],
            'user' => [
                'secret:read', 'secret:write', 'secret:share',
                'log:read_own',
                'profile:read', 'profile:write'
            ],
            'viewer' => [
                'secret:read',
                'log:read_own',
                'profile:read'
            ]
        ];
    }

    // Utility methods
    public function getDisplayName(): string
    {
        return $this->name ?: explode('@', $this->email)[0];
    }

    public function getInitials(): string
    {
        $name = $this->getName();
        $words = explode(' ', $name);
        $initials = '';

        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper($word[0]);
            }
        }

        return $initials ?: strtoupper(substr($this->email, 0, 2));
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->getName(),
            'role' => $this->role,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'last_login_at' => $this->lastLoginAt?->format('Y-m-d H:i:s'),
            'display_name' => $this->getDisplayName(),
            'initials' => $this->getInitials()
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // Validation methods
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one digit';
        }

        return $errors;
    }

    // Factory methods
    public static function fromArray(array $data): self
    {
        $user = new self(
            $data['email'],
            '', // Will be set separately
            $data['name'] ?? '',
            $data['role'] ?? 'user'
        );

        if (isset($data['id'])) {
            $user->setId($data['id']);
        }

        if (isset($data['password_hash'])) {
            $user->passwordHash = $data['password_hash'];
        }

        if (isset($data['is_active'])) {
            $user->setActive((bool)$data['is_active']);
        }

        if (isset($data['created_at'])) {
            $user->createdAt = new \DateTime($data['created_at']);
        }

        if (isset($data['last_login_at'])) {
            $user->lastLoginAt = new \DateTime($data['last_login_at']);
        }

        if (isset($data['preferences'])) {
            $user->setPreferences($data['preferences']);
        }

        if (isset($data['reset_token'])) {
            $user->resetToken = $data['reset_token'];
        }

        if (isset($data['reset_token_expires'])) {
            $user->resetTokenExpires = new \DateTime($data['reset_token_expires']);
        }

        return $user;
    }
}