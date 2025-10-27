<?php

namespace App\Models;

use JsonSerializable;

class UserSecret implements JsonSerializable
{
    private ?int $id = null;
    private int $userId;
    private string $key;
    private string $encryptedValue;
    private ?string $decryptedValue = null;
    private string $description;
    private string $category;
    private bool $isDeleted;
    private ?int $sharedBy;
    private array $accessList;
    private \DateTime $createdAt;
    private \DateTime $updatedAt;
    private ?\DateTime $expiresAt = null;
    private array $metadata;
    private int $accessCount;
    private ?\DateTime $lastAccessed = null;

    public function __construct(
        int $userId,
        string $key,
        string $encryptedValue,
        string $description = '',
        string $category = 'general'
    ) {
        $this->userId = $userId;
        $this->key = trim($key);
        $this->encryptedValue = $encryptedValue;
        $this->description = $description;
        $this->category = $this->validateCategory($category);
        $this->isDeleted = false;
        $this->sharedBy = null;
        $this->accessList = [];
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->metadata = [];
        $this->accessCount = 0;
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = trim($key);
        $this->updatedAt = new \DateTime();
    }

    public function getEncryptedValue(): string
    {
        return $this->encryptedValue;
    }

    public function setEncryptedValue(string $encryptedValue): void
    {
        $this->encryptedValue = $encryptedValue;
        $this->decryptedValue = null; // Clear cached decrypted value
        $this->updatedAt = new \DateTime();
    }

    public function getDecryptedValue(): ?string
    {
        return $this->decryptedValue;
    }

    public function setDecryptedValue(string $decryptedValue): void
    {
        $this->decryptedValue = $decryptedValue;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new \DateTime();
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $this->validateCategory($category);
        $this->updatedAt = new \DateTime();
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setDeleted(bool $isDeleted): void
    {
        $this->isDeleted = $isDeleted;
        $this->updatedAt = new \DateTime();
    }

    public function getSharedBy(): ?int
    {
        return $this->sharedBy;
    }

    public function setSharedBy(?int $sharedBy): void
    {
        $this->sharedBy = $sharedBy;
    }

    public function getAccessList(): array
    {
        return $this->accessList;
    }

    public function setAccessList(array $accessList): void
    {
        $this->accessList = array_unique($accessList);
        $this->updatedAt = new \DateTime();
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function getExpiresAt(): ?\DateTime
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTime $expiresAt): void
    {
        $this->expiresAt = $expiresAt;
        $this->updatedAt = new \DateTime();
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->updatedAt = new \DateTime();
    }

    public function getAccessCount(): int
    {
        return $this->accessCount;
    }

    public function getLastAccessed(): ?\DateTime
    {
        return $this->lastAccessed;
    }

    // Access control methods
    public function canBeAccessedBy(int $userId): bool
    {
        // Owner can always access (if not deleted)
        if ($this->userId === $userId && !$this->isDeleted) {
            return true;
        }

        // Check if user is in access list and secret is not deleted
        if (!$this->isDeleted && in_array($userId, $this->accessList)) {
            return true;
        }

        return false;
    }

    public function isOwner(int $userId): bool
    {
        return $this->userId === $userId;
    }

    public function canBeSharedBy(int $userId): bool
    {
        // Only owner can share
        return $this->userId === $userId && !$this->isDeleted;
    }

    public function canBeDeletedBy(int $userId): bool
    {
        // Only owner can delete
        return $this->userId === $userId;
    }

    // Access management methods
    public function addToAccessList(int $userId): bool
    {
        if (!in_array($userId, $this->accessList)) {
            $this->accessList[] = $userId;
            $this->updatedAt = new \DateTime();
            return true;
        }
        return false;
    }

    public function removeFromAccessList(int $userId): bool
    {
        $key = array_search($userId, $this->accessList);
        if ($key !== false) {
            unset($this->accessList[$key]);
            $this->accessList = array_values($this->accessList); // Re-index
            $this->updatedAt = new \DateTime();
            return true;
        }
        return false;
    }

    public function hasAccess(int $userId): bool
    {
        return in_array($userId, $this->accessList);
    }

    public function getSharedUsersCount(): int
    {
        return count($this->accessList);
    }

    // Lifecycle methods
    public function recordAccess(): void
    {
        $this->accessCount++;
        $this->lastAccessed = new \DateTime();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTime();
    }

    public function getDaysUntilExpiry(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }

        $now = new \DateTime();
        $interval = $now->diff($this->expiresAt);
        return (int)$interval->format('%r%a'); // Negative if expired
    }

    // Validation methods
    private function validateCategory(string $category): string
    {
        $allowedCategories = [
            'api_keys',
            'database',
            'credentials',
            'tokens',
            'ssh_keys',
            'certificates',
            'passwords',
            'general'
        ];

        return in_array($category, $allowedCategories) ? $category : 'general';
    }

    public static function validateKey(string $key): array
    {
        $errors = [];

        if (empty(trim($key))) {
            $errors[] = 'Secret key cannot be empty';
        }

        if (strlen($key) > 255) {
            $errors[] = 'Secret key cannot be longer than 255 characters';
        }

        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key)) {
            $errors[] = 'Secret key can only contain letters, numbers, dots, hyphens, and underscores';
        }

        return $errors;
    }

    // Utility methods
    public function getCategoryIcon(): string
    {
        $icons = [
            'api_keys' => '🔑',
            'database' => '🗄️',
            'credentials' => '🔐',
            'tokens' => '🎫',
            'ssh_keys' => '🖥️',
            'certificates' => '📜',
            'passwords' => '🔒',
            'general' => '📝'
        ];

        return $icons[$this->category] ?? '📝';
    }

    public function getCategoryColor(): string
    {
        $colors = [
            'api_keys' => '#FF6B6B',
            'database' => '#4ECDC4',
            'credentials' => '#45B7D1',
            'tokens' => '#96CEB4',
            'ssh_keys' => '#FFEAA7',
            'certificates' => '#DDA0DD',
            'passwords' => '#98D8C8',
            'general' => '#95A5A6'
        ];

        return $colors[$this->category] ?? '#95A5A6';
    }

    public function getSecurityLevel(): string
    {
        if ($this->expiresAt && $this->getDaysUntilExpiry() < 7) {
            return 'high';
        }

        if ($this->accessCount > 100) {
            return 'medium';
        }

        return 'low';
    }

    public function toArray(bool $includeDecrypted = false): array
    {
        $data = [
            'id' => $this->id,
            'user_id' => $this->userId,
            'key' => $this->key,
            'description' => $this->description,
            'category' => $this->category,
            'category_icon' => $this->getCategoryIcon(),
            'category_color' => $this->getCategoryColor(),
            'is_deleted' => $this->isDeleted,
            'shared_by' => $this->sharedBy,
            'shared_users_count' => $this->getSharedUsersCount(),
            'access_list' => $this->accessList,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'expires_at' => $this->expiresAt?->format('Y-m-d H:i:s'),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'is_expired' => $this->isExpired(),
            'access_count' => $this->accessCount,
            'last_accessed' => $this->lastAccessed?->format('Y-m-d H:i:s'),
            'security_level' => $this->getSecurityLevel(),
            'metadata' => $this->metadata
        ];

        if ($includeDecrypted && $this->decryptedValue !== null) {
            $data['value'] = $this->decryptedValue;
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray(false);
    }

    // Factory methods
    public static function fromArray(array $data): self
    {
        $secret = new self(
            $data['user_id'],
            $data['key'],
            $data['encrypted_value'],
            $data['description'] ?? '',
            $data['category'] ?? 'general'
        );

        if (isset($data['id'])) {
            $secret->setId($data['id']);
        }

        if (isset($data['is_deleted'])) {
            $secret->setDeleted((bool)$data['is_deleted']);
        }

        if (isset($data['shared_by'])) {
            $secret->setSharedBy($data['shared_by']);
        }

        if (isset($data['access_list'])) {
            $secret->setAccessList($data['access_list']);
        }

        if (isset($data['created_at'])) {
            $secret->createdAt = new \DateTime($data['created_at']);
        }

        if (isset($data['updated_at'])) {
            $secret->updatedAt = new \DateTime($data['updated_at']);
        }

        if (isset($data['expires_at'])) {
            $secret->setExpiresAt(new \DateTime($data['expires_at']));
        }

        if (isset($data['metadata'])) {
            $secret->setMetadata($data['metadata']);
        }

        if (isset($data['access_count'])) {
            $secret->accessCount = $data['access_count'];
        }

        if (isset($data['last_accessed'])) {
            $secret->lastAccessed = new \DateTime($data['last_accessed']);
        }

        return $secret;
    }

    // Search and filter methods
    public function matchesSearch(string $query): bool
    {
        $query = strtolower(trim($query));

        if (empty($query)) {
            return true;
        }

        return stripos($this->key, $query) !== false ||
               stripos($this->description, $query) !== false ||
               stripos($this->category, $query) !== false;
    }

    public function isInCategory(string $category): bool
    {
        return $this->category === $category;
    }

    public function isShared(): bool
    {
        return !empty($this->accessList);
    }

    public function isOwnerSecret(): bool
    {
        return $this->sharedBy === null;
    }
}