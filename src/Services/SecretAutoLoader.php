<?php

namespace App\Services;

use App\Context\UserContext;
use App\Models\User;
use App\Models\UserSecret;
use App\Services\UserAwareLogger;

class SecretAutoLoader
{
    private UserContext $userContext;
    private UserSecretService $userSecretService;
    private SecretManagerService $secretManagerService;
    private UserAwareLogger $logger;
    private bool $autoLoadEnabled = true;
    private array $loadedSecrets = [];

    public function __construct(
        UserContext $userContext,
        UserSecretService $userSecretService,
        SecretManagerService $secretManagerService,
        UserAwareLogger $logger
    ) {
        $this->userContext = $userContext;
        $this->userSecretService = $userSecretService;
        $this->secretManagerService = $secretManagerService;
        $this->logger = $logger;
        $this->logger->setUserContext($userContext);
    }

    /**
     * Automatically load all user secrets into the main secret system
     */
    public function loadUserSecrets(): array
    {
        if (!$this->autoLoadEnabled || !$this->userContext->isAuthenticated()) {
            return [];
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            $this->logger->logUserActivity('auto_load_secrets_start', [
                'user_id' => $userId,
                'user_email' => $user->getEmail()
            ]);

            // Get all user secrets (owned and shared)
            $userSecrets = $this->userSecretService->getUserSecrets();
            $loadedCount = 0;
            $errorCount = 0;
            $this->loadedSecrets = [];

            foreach ($userSecrets as $userSecret) {
                try {
                    // Get decrypted value
                    $secretValue = $userSecret->getDecryptedValue();

                    if ($secretValue === null) {
                        // Decrypt if needed
                        $secretValue = $this->decryptUserSecret($userSecret);
                    }

                    // Load into main secret system with user context prefix
                    $secretKey = $this->formatSecretKey($userSecret, $user);

                    // Add to SecretManagerService
                    $this->secretManagerService->setSecret(
                        $secretKey,
                        $secretValue,
                        $userSecret->getDescription()
                    );

                    $this->loadedSecrets[] = [
                        'key' => $secretKey,
                        'original_key' => $userSecret->getKey(),
                        'category' => $userSecret->getCategory(),
                        'is_owner' => $userSecret->isOwner($userId),
                        'is_shared' => $userSecret->isShared(),
                        'expires_at' => $userSecret->getExpiresAt()?->format('Y-m-d H:i:s'),
                        'access_count' => $userSecret->getAccessCount()
                    ];

                    $loadedCount++;

                } catch (\Exception $e) {
                    $errorCount++;
                    $this->logger->logSecretOperation('auto_load_failed', $userSecret->getKey(), [
                        'user_id' => $userId,
                        'error' => $e->getMessage(),
                        'category' => $userSecret->getCategory()
                    ]);
                }
            }

            $this->logger->logUserActivity('auto_load_secrets_complete', [
                'user_id' => $userId,
                'loaded_count' => $loadedCount,
                'error_count' => $errorCount,
                'total_secrets' => count($userSecrets),
                'categories' => array_unique(array_column($this->loadedSecrets, 'category'))
            ]);

            return [
                'loaded_count' => $loadedCount,
                'error_count' => $errorCount,
                'secrets' => $this->loadedSecrets
            ];

        } catch (\Exception $e) {
            $this->logger->logUserActivity('auto_load_secrets_error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get the list of loaded secrets
     */
    public function getLoadedSecrets(): array
    {
        return $this->loadedSecrets;
    }

    /**
     * Check if a specific secret is loaded
     */
    public function isSecretLoaded(string $key): bool
    {
        foreach ($this->loadedSecrets as $secret) {
            if ($secret['key'] === $key || $secret['original_key'] === $key) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get loaded secret info
     */
    public function getLoadedSecretInfo(string $key): ?array
    {
        foreach ($this->loadedSecrets as $secret) {
            if ($secret['key'] === $key || $secret['original_key'] === $key) {
                return $secret;
            }
        }
        return null;
    }

    /**
     * Enable/disable auto-loading
     */
    public function setAutoLoadEnabled(bool $enabled): void
    {
        $this->autoLoadEnabled = $enabled;

        if ($this->userContext->isAuthenticated()) {
            $this->logger->logUserActivity('auto_load_toggle', [
                'user_id' => $this->userContext->getUser()->getId(),
                'enabled' => $enabled
            ]);
        }
    }

    /**
     * Check if auto-loading is enabled
     */
    public function isAutoLoadEnabled(): bool
    {
        return $this->autoLoadEnabled;
    }

    /**
     * Reload secrets from database
     */
    public function reloadSecrets(): array
    {
        // Clear current loaded secrets
        $this->clearLoadedSecrets();

        // Reload from database
        return $this->loadUserSecrets();
    }

    /**
     * Clear loaded secrets from main system
     */
    public function clearLoadedSecrets(): void
    {
        if (!$this->userContext->isAuthenticated()) {
            return;
        }

        $userId = $this->userContext->getUser()->getId();
        $clearedCount = 0;

        foreach ($this->loadedSecrets as $secret) {
            try {
                $this->secretManagerService->deleteSecret($secret['key']);
                $clearedCount++;
            } catch (\Exception $e) {
                $this->logger->logSecretOperation('clear_loaded_failed', $secret['key'], [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->loadedSecrets = [];

        $this->logger->logUserActivity('auto_load_clear', [
            'user_id' => $userId,
            'cleared_count' => $clearedCount
        ]);
    }

    /**
     * Sync a single secret change from user secrets to main system
     */
    public function syncSecretChange(string $secretKey, string $operation): bool
    {
        if (!$this->userContext->isAuthenticated() || !$this->autoLoadEnabled) {
            return false;
        }

        $user = $this->userContext->getUser();
        $userId = $user->getId();

        try {
            switch ($operation) {
                case 'create':
                case 'update':
                    return $this->syncSingleSecret($secretKey);

                case 'delete':
                    return $this->removeSecret($secretKey);

                default:
                    $this->logger->logSecretOperation('sync_unknown_operation', $secretKey, [
                        'user_id' => $userId,
                        'operation' => $operation
                    ]);
                    return false;
            }

        } catch (\Exception $e) {
            $this->logger->logSecretOperation('sync_failed', $secretKey, [
                'user_id' => $userId,
                'operation' => $operation,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get statistics about loaded secrets
     */
    public function getLoadedSecretsStats(): array
    {
        if (empty($this->loadedSecrets)) {
            return [
                'total_count' => 0,
                'owned_count' => 0,
                'shared_count' => 0,
                'categories' => [],
                'expiring_soon' => 0,
                'expired' => 0
            ];
        }

        $stats = [
            'total_count' => count($this->loadedSecrets),
            'owned_count' => 0,
            'shared_count' => 0,
            'categories' => [],
            'expiring_soon' => 0,
            'expired' => 0
        ];

        $now = new \DateTime();
        $sevenDaysFromNow = (clone $now)->modify('+7 days');

        foreach ($this->loadedSecrets as $secret) {
            // Ownership stats
            if ($secret['is_owner']) {
                $stats['owned_count']++;
            } else {
                $stats['shared_count']++;
            }

            // Category stats
            $category = $secret['category'];
            $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;

            // Expiration stats
            if ($secret['expires_at']) {
                $expiresAt = new \DateTime($secret['expires_at']);

                if ($expiresAt < $now) {
                    $stats['expired']++;
                } elseif ($expiresAt <= $sevenDaysFromNow) {
                    $stats['expiring_soon']++;
                }
            }
        }

        arsort($stats['categories']);
        return $stats;
    }

    // Private helper methods

    private function formatSecretKey(UserSecret $userSecret, User $user): string
    {
        $prefix = $userSecret->isOwner($user->getId()) ? 'user' : 'shared';
        $category = $userSecret->getCategory();

        return "{$prefix}:{$category}:{$userSecret->getKey()}";
    }

    private function decryptUserSecret(UserSecret $userSecret): string
    {
        // This would use the UserSecretService's decryption logic
        // For now, assume the secret has a method to get decrypted value
        return $userSecret->getDecryptedValue() ?? '';
    }

    private function syncSingleSecret(string $secretKey): bool
    {
        try {
            $userSecret = $this->userSecretService->getSecret($secretKey);

            if (!$userSecret) {
                // Secret was deleted, remove from main system
                return $this->removeSecret($secretKey);
            }

            $user = $this->userContext->getUser();
            $formattedKey = $this->formatSecretKey($userSecret, $user);
            $secretValue = $this->decryptUserSecret($userSecret);

            // Update or create in main system
            $this->secretManagerService->setSecret(
                $formattedKey,
                $secretValue,
                $userSecret->getDescription()
            );

            // Update loaded secrets tracking
            $this->updateLoadedSecretTracking($userSecret, $user);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    private function removeSecret(string $secretKey): bool
    {
        $user = $this->userContext->getUser();

        // Find and remove from loaded secrets
        foreach ($this->loadedSecrets as $index => $secret) {
            if ($secret['original_key'] === $secretKey) {
                try {
                    $this->secretManagerService->deleteSecret($secret['key']);
                    unset($this->loadedSecrets[$index]);
                    return true;
                } catch (\Exception $e) {
                    return false;
                }
            }
        }

        return false;
    }

    private function updateLoadedSecretTracking(UserSecret $userSecret, User $user): void
    {
        $formattedKey = $this->formatSecretKey($userSecret, $user);

        // Update existing entry or add new one
        foreach ($this->loadedSecrets as &$secret) {
            if ($secret['original_key'] === $userSecret->getKey()) {
                $secret = [
                    'key' => $formattedKey,
                    'original_key' => $userSecret->getKey(),
                    'category' => $userSecret->getCategory(),
                    'is_owner' => $userSecret->isOwner($user->getId()),
                    'is_shared' => $userSecret->isShared(),
                    'expires_at' => $userSecret->getExpiresAt()?->format('Y-m-d H:i:s'),
                    'access_count' => $userSecret->getAccessCount()
                ];
                return;
            }
        }

        // Add new entry if not found
        $this->loadedSecrets[] = [
            'key' => $formattedKey,
            'original_key' => $userSecret->getKey(),
            'category' => $userSecret->getCategory(),
            'is_owner' => $userSecret->isOwner($user->getId()),
            'is_shared' => $userSecret->isShared(),
            'expires_at' => $userSecret->getExpiresAt()?->format('Y-m-d H:i:s'),
            'access_count' => $userSecret->getAccessCount()
        ];
    }
}