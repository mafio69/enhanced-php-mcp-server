<?php

namespace App\Services;

use App\Context\UserContext;
use App\Models\User;
use App\Models\UserSecret;
use App\Services\UserAwareLogger;
use Exception;

class UserSecretService extends SecretService
{
    private UserContext $userContext;
    private UserAwareLogger $logger;
    private array $userSecrets = []; // In-memory storage (replace with database)

    public function __construct(UserContext $userContext, UserAwareLogger $logger)
    {
        $this->userContext = $userContext;
        $this->logger = $logger;
        $this->logger->setUserContext($userContext);
    }

    public function storeSecret(string $key, string $value, string $description = '', string $category = 'general', ?\DateTime $expiresAt = null): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        // Validate inputs
        $keyErrors = UserSecret::validateKey($key);
        if (!empty($keyErrors)) {
            throw new \InvalidArgumentException('Invalid secret key: ' . implode(', ', $keyErrors));
        }

        if (empty($value)) {
            throw new \InvalidArgumentException('Secret value cannot be empty');
        }

        try {
            // Check if secret already exists for this user
            $existingSecret = $this->findUserSecret($userId, $key);
            if ($existingSecret && !$existingSecret->isDeleted()) {
                throw new \RuntimeException("Secret '{$key}' already exists");
            }

            // Szyfrowanie z kluczem użytkownika
            $encryptedValue = $this->encryptWithUserKey($value, $user);

            $secret = new UserSecret(
                userId: $userId,
                key: $key,
                encryptedValue: $encryptedValue,
                description: $description,
                category: $category
            );

            if ($expiresAt) {
                $secret->setExpiresAt($expiresAt);
            }

            // Store in memory (replace with database save)
            $this->userSecrets["{$userId}:{$key}"] = $secret;

            // Logowanie operacji
            $this->logger->logSecretOperation('store', $key, [
                'user_id' => $userId,
                'category' => $category,
                'description' => $description,
                'expires_at' => $expiresAt?->format('Y-m-d H:i:s')
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->logSecretOperation('store_failed', $key, [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'category' => $category
            ]);

            throw $e;
        }
    }

    public function getSecret(string $key): ?UserSecret
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $secret = $this->findUserSecret($userId, $key);

            if (!$secret) {
                // Check if it's shared with the user
                $secret = $this->findSharedSecret($userId, $key);
            }

            if (!$secret || !$secret->canBeAccessedBy($userId)) {
                $this->logger->logSecretOperation('access_denied', $key, [
                    'user_id' => $userId,
                    'reason' => 'not_found_or_no_access'
                ]);
                return null;
            }

            // Check if secret is expired
            if ($secret->isExpired()) {
                $this->logger->logSecretOperation('access_denied', $key, [
                    'user_id' => $userId,
                    'reason' => 'secret_expired'
                ]);
                return null;
            }

            // Deszyfrowanie
            $decryptedValue = $this->decryptWithUserKey($secret->getEncryptedValue(), $user);
            $secret->setDecryptedValue($decryptedValue);

            // Record access
            $secret->recordAccess();

            // Logowanie dostępu
            $this->logger->logSecretOperation('access', $key, [
                'user_id' => $userId,
                'is_owner' => $secret->isOwner($userId),
                'access_count' => $secret->getAccessCount(),
                'is_shared' => $secret->isShared()
            ]);

            return $secret;

        } catch (Exception $e) {
            $this->logger->logSecretOperation('access_failed', $key, [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateSecret(string $key, string $newValue, ?string $description = null, ?string $category = null): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $secret = $this->findUserSecret($userId, $key);

            if (!$secret || !$secret->isOwner($userId)) {
                throw new \RuntimeException("Secret '{$key}' not found or no permission to update");
            }

            // Szyfrowanie nowej wartości
            $encryptedValue = $this->encryptWithUserKey($newValue, $user);
            $secret->setEncryptedValue($encryptedValue);

            if ($description !== null) {
                $secret->setDescription($description);
            }

            if ($category !== null) {
                $secret->setCategory($category);
            }

            // Update in memory storage
            $this->userSecrets["{$userId}:{$key}"] = $secret;

            $this->logger->logSecretOperation('update', $key, [
                'user_id' => $userId,
                'description_updated' => $description !== null,
                'category_updated' => $category !== null
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->logSecretOperation('update_failed', $key, [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function deleteSecret(string $key): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $secret = $this->findUserSecret($userId, $key);

            if (!$secret || !$secret->canBeDeletedBy($userId)) {
                throw new \RuntimeException("Secret '{$key}' not found or no permission to delete");
            }

            // Soft delete
            $secret->setDeleted(true);

            // Update in memory storage
            $this->userSecrets["{$userId}:{$key}"] = $secret;

            $this->logger->logSecretOperation('delete', $key, [
                'user_id' => $userId,
                'is_owner' => $secret->isOwner($userId)
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->logSecretOperation('delete_failed', $key, [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function shareSecretWithUser(string $secretKey, int $targetUserId): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $secret = $this->findUserSecret($userId, $secretKey);

            if (!$secret || !$secret->canBeSharedBy($userId)) {
                throw new \RuntimeException("Cannot share secret '{$secretKey}' - not owner or not found");
            }

            // Check if target user exists
            $targetUser = $this->findUserById($targetUserId);
            if (!$targetUser) {
                throw new \RuntimeException("Target user not found");
            }

            // Add to access list
            $added = $secret->addToAccessList($targetUserId);
            if (!$added) {
                throw new \RuntimeException("User already has access to this secret");
            }

            // Update storage
            $this->userSecrets["{$userId}:{$secretKey}"] = $secret;

            $this->logger->logSecretOperation('share', $secretKey, [
                'owner_id' => $userId,
                'target_user_id' => $targetUserId,
                'target_email' => $targetUser->getEmail(),
                'access_list_size' => $secret->getSharedUsersCount()
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->logSecretOperation('share_failed', $secretKey, [
                'owner_id' => $userId,
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function shareSecretWithEmail(string $secretKey, string $email): bool
    {
        // Find user by email
        $targetUser = $this->findUserByEmail($email);
        if (!$targetUser) {
            throw new \RuntimeException("User with email '{$email}' not found");
        }

        return $this->shareSecretWithUser($secretKey, $targetUser->getId());
    }

    public function revokeSecretAccess(string $secretKey, int $targetUserId): bool
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $secret = $this->findUserSecret($userId, $secretKey);

            if (!$secret || !$secret->isOwner($userId)) {
                throw new \RuntimeException("Cannot revoke access - not owner");
            }

            // Remove from access list
            $removed = $secret->removeFromAccessList($targetUserId);
            if (!$removed) {
                throw new \RuntimeException("User does not have access to this secret");
            }

            // Update storage
            $this->userSecrets["{$userId}:{$secretKey}"] = $secret;

            $this->logger->logSecretOperation('revoke_access', $secretKey, [
                'owner_id' => $userId,
                'target_user_id' => $targetUserId,
                'access_list_size' => $secret->getSharedUsersCount()
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->logSecretOperation('revoke_access_failed', $secretKey, [
                'owner_id' => $userId,
                'target_user_id' => $targetUserId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getUserSecrets(array $filters = []): array
    {
        if (!$this->userContext->isAuthenticated()) {
            throw new \RuntimeException('User not authenticated');
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $secrets = [];

            // Get user's own secrets
            foreach ($this->userSecrets as $key => $secret) {
                if (strpos($key, "{$userId}:") === 0 && !$secret->isDeleted()) {
                    if ($this->matchesFilters($secret, $filters)) {
                        $secrets[] = $secret;
                    }
                }
            }

            // Get shared secrets
            foreach ($this->userSecrets as $key => $secret) {
                if (strpos($key, "{$userId}:") !== 0 && !$secret->isDeleted()) {
                    if ($secret->canBeAccessedBy($userId) && $this->matchesFilters($secret, $filters)) {
                        // Mark as shared
                        $secret->setSharedBy($this->extractUserIdFromKey($key));
                        $secrets[] = $secret;
                    }
                }
            }

            // Sort by creation date (newest first)
            usort($secrets, function($a, $b) {
                return $b->getCreatedAt() <=> $a->getCreatedAt();
            });

            $this->logger->logUserActivity('list_secrets', [
                'count' => count($secrets),
                'filters' => $filters,
                'categories' => array_unique(array_map(fn($s) => $s->getCategory(), $secrets))
            ]);

            return $secrets;

        } catch (Exception $e) {
            $this->logger->logUserActivity('list_secrets_failed', [
                'error' => $e->getMessage(),
                'filters' => $filters
            ]);
            throw $e;
        }
    }

    public function getSecretCategories(): array
    {
        if (!$this->userContext->isAuthenticated()) {
            return [];
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        $categories = [];
        foreach ($this->userSecrets as $key => $secret) {
            if (strpos($key, "{$userId}:") === 0 && !$secret->isDeleted()) {
                $category = $secret->getCategory();
                $categories[$category] = ($categories[$category] ?? 0) + 1;
            }
        }

        arsort($categories);
        return $categories;
    }

    public function getSecretStats(): array
    {
        if (!$this->userContext->isAuthenticated()) {
            return [];
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        $stats = [
            'total_secrets' => 0,
            'owned_secrets' => 0,
            'shared_secrets' => 0,
            'shared_with_me' => 0,
            'expired_secrets' => 0,
            'expiring_soon' => 0,
            'categories' => [],
            'recent_activity' => []
        ];

        foreach ($this->userSecrets as $key => $secret) {
            if ($secret->isDeleted()) {
                continue;
            }

            $isOwner = strpos($key, "{$userId}:") === 0;
            $hasAccess = $secret->canBeAccessedBy($userId);

            if ($hasAccess) {
                $stats['total_secrets']++;

                if ($isOwner) {
                    $stats['owned_secrets']++;
                    if ($secret->isShared()) {
                        $stats['shared_secrets']++;
                    }
                } else {
                    $stats['shared_with_me']++;
                }

                // Category stats
                $category = $secret->getCategory();
                $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;

                // Expiration stats
                if ($secret->isExpired()) {
                    $stats['expired_secrets']++;
                } elseif ($secret->getExpiresAt()) {
                    $daysUntilExpiry = $secret->getDaysUntilExpiry();
                    if ($daysUntilExpiry !== null && $daysUntilExpiry <= 7) {
                        $stats['expiring_soon']++;
                    }
                }

                // Recent activity (last accessed in last 7 days)
                if ($secret->getLastAccessed()) {
                    $daysSinceAccess = (new \DateTime())->diff($secret->getLastAccessed())->days;
                    if ($daysSinceAccess <= 7) {
                        $stats['recent_activity'][] = [
                            'secret_key' => $secret->getKey(),
                            'last_accessed' => $secret->getLastAccessed()->format('Y-m-d H:i:s'),
                            'access_count' => $secret->getAccessCount(),
                            'is_owner' => $isOwner
                        ];
                    }
                }
            }
        }

        // Sort recent activity by last accessed date
        usort($stats['recent_activity'], function($a, $b) {
            return strtotime($b['last_accessed']) - strtotime($a['last_accessed']);
        });

        // Limit to 10 recent activities
        $stats['recent_activity'] = array_slice($stats['recent_activity'], 0, 10);

        return $stats;
    }

    // Private helper methods
    private function findUserSecret(int $userId, string $key): ?UserSecret
    {
        return $this->userSecrets["{$userId}:{$key}"] ?? null;
    }

    private function findSharedSecret(int $userId, string $key): ?UserSecret
    {
        foreach ($this->userSecrets as $storageKey => $secret) {
            if ($secret->getKey() === $key && $secret->canBeAccessedBy($userId)) {
                return $secret;
            }
        }
        return null;
    }

    private function encryptWithUserKey(string $value, User $user): string
    {
        // Generate user-specific encryption key
        $userKey = $this->getUserEncryptionKey($user);
        return $this->encrypt($value, $userKey);
    }

    private function decryptWithUserKey(string $encryptedValue, User $user): string
    {
        // Generate user-specific encryption key
        $userKey = $this->getUserEncryptionKey($user);
        return $this->decrypt($encryptedValue, $userKey);
    }

    private function getUserEncryptionKey(User $user): string
    {
        // Generate encryption key based on user ID and password hash
        // In production, use a more sophisticated key derivation
        return hash('sha256', $user->getId() . $user->getPasswordHash() . $user->getCreatedAt()->format('Y-m-d'));
    }

    private function matchesFilters(UserSecret $secret, array $filters): bool
    {
        // Category filter
        if (isset($filters['category']) && $secret->getCategory() !== $filters['category']) {
            return false;
        }

        // Search filter
        if (isset($filters['search']) && !$secret->matchesSearch($filters['search'])) {
            return false;
        }

        // Shared filter
        if (isset($filters['shared'])) {
            $isShared = $secret->isShared();
            if ($filters['shared'] === 'true' && !$isShared) {
                return false;
            } elseif ($filters['shared'] === 'false' && $isShared) {
                return false;
            }
        }

        // Expiration filter
        if (isset($filters['expires'])) {
            if ($filters['expires'] === 'expired' && !$secret->isExpired()) {
                return false;
            } elseif ($filters['expires'] === 'expiring' && ($secret->isExpired() || !$secret->getExpiresAt() || $secret->getDaysUntilExpiry() > 7)) {
                return false;
            } elseif ($filters['expires'] === 'never' && $secret->getExpiresAt()) {
                return false;
            }
        }

        return true;
    }

    private function extractUserIdFromKey(string $storageKey): int
    {
        return (int) explode(':', $storageKey)[0];
    }

    // Database simulation methods (replace with actual database implementation)
    private function findUserById(int $id): ?User
    {
        // In a real implementation, query database
        return null;
    }

    private function findUserByEmail(string $email): ?User
    {
        // In a real implementation, query database
        return null;
    }

    // Encryption methods (using parent class methods)
    private function encrypt(string $data, string $key): string
    {
        // Use parent encryption method or implement user-specific encryption
        return parent::encryptValue($data); // This would need to be modified
    }

    private function decrypt(string $encryptedData, string $key): string
    {
        // Use parent decryption method or implement user-specific decryption
        return parent::decryptValue($encryptedData); // This would need to be modified
    }
}