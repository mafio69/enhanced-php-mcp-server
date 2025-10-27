<?php

namespace App\Services;

use App\DTO\SecretDTO;
use Exception;
use Psr\Log\LoggerInterface;

class SecretService
{
    private LoggerInterface $logger;
    private array $secrets = [];
    private string $secretsFile;
    private string $encryptionKey;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->secretsFile = __DIR__ . '/../../config/secrets.json';
        $this->encryptionKey = $this->getOrCreateEncryptionKey();
        $this->loadSecrets();
    }

    public function listSecrets(): array
    {
        try {
            $secrets = [];

            // Load from primary storage (config/secrets.json)
            foreach ($this->secrets as $key => $data) {
                $secrets[$key] = [
                    'name' => $key,
                    'description' => $data['description'] ?? '',
                    'created_at' => $data['created_at'] ?? '',
                    'has_value' => !empty($data['encrypted_value']),
                    'last_accessed' => $data['last_accessed'] ?? null
                ];
            }

            // Also try to load from SecretManagerService storage
            try {
                if (class_exists('App\Services\SecretManagerService')) {
                    $secretManager = new \App\Services\SecretManagerService($this->logger);
                    $managerSecrets = $secretManager->listSecrets();

                    foreach ($managerSecrets as $key) {
                        // Convert dots back to underscores for consistency
                        $normalizedKey = str_replace('.', '_', $key);
                        if (!isset($secrets[$normalizedKey])) {
                            $secrets[$normalizedKey] = [
                                'name' => $normalizedKey,
                                'description' => 'Migrated from SecretManager',
                                'created_at' => 'Unknown',
                                'has_value' => true,
                                'last_accessed' => null
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                // SecretManager integration failed, continue with primary storage
                $this->logger->debug('SecretManager integration failed', ['error' => $e->getMessage()]);
            }

            $this->logger->info('Secrets list accessed', ['count' => count($secrets)]);
            return array_values($secrets); // Return as indexed array for JSON compatibility
        } catch (Exception $e) {
            $this->logger->error('Failed to list secrets', ['error' => $e->getMessage()]);
            throw new Exception('Failed to list secrets: ' . $e->getMessage());
        }
    }

    public function getSecret(string $key): ?SecretDTO
    {
        try {
            // First try to get from SecretManagerService
            try {
                if (class_exists('App\Services\SecretManagerService')) {
                    $secretManager = new \App\Services\SecretManagerService($this->logger);
                    $managerSecret = $secretManager->getSecret($key);

                    if ($managerSecret !== null) {
                        $this->logger->info('Secret accessed from SecretManager', ['key' => $key]);
                        return new SecretDTO($key, $managerSecret, 'Retrieved from SecretManager');
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug('SecretManager access failed', ['key' => $key, 'error' => $e->getMessage()]);
            }

            // Then try to get from primary storage (config/secrets.json)
            if (isset($this->secrets[$key]) && !empty($this->secrets[$key]['encrypted_value'])) {
                $secretData = $this->secrets[$key];
                $decryptedValue = $this->decrypt($secretData['encrypted_value']);

                // Update last accessed
                $this->secrets[$key]['last_accessed'] = date('Y-m-d H:i:s');
                $this->saveSecrets();

                $this->logger->info('Secret accessed from primary storage', ['key' => $key]);
                return new SecretDTO($key, $decryptedValue, $secretData['description'] ?? '');
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error('Failed to get secret', ['key' => $key, 'error' => $e->getMessage()]);
            throw new Exception('Failed to get secret: ' . $e->getMessage());
        }
    }

    public function storeSecret(string $key, string $value, string $description = ''): bool
    {
        try {
            if (empty($key) || empty($value)) {
                throw new Exception('Key and value are required');
            }

            $encryptedValue = $this->encrypt($value);

            $this->secrets[$key] = [
                'encrypted_value' => $encryptedValue,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $this->saveSecrets();
            $this->logger->info('Secret stored', ['key' => $key]);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to store secret', ['key' => $key, 'error' => $e->getMessage()]);
            throw new Exception('Failed to store secret: ' . $e->getMessage());
        }
    }

    public function deleteSecret(string $key): bool
    {
        try {
            if (!$this->secretExists($key)) {
                return false;
            }

            unset($this->secrets[$key]);
            $this->saveSecrets();
            $this->logger->info('Secret deleted', ['key' => $key]);
            return true;
        } catch (Exception $e) {
            $this->logger->error('Failed to delete secret', ['key' => $key, 'error' => $e->getMessage()]);
            throw new Exception('Failed to delete secret: ' . $e->getMessage());
        }
    }

    public function checkSecret(string $key): bool
    {
        return $this->secretExists($key);
    }

    public function encryptValue(string $value): string
    {
        try {
            return $this->encrypt($value);
        } catch (Exception $e) {
            $this->logger->error('Failed to encrypt value', ['error' => $e->getMessage()]);
            throw new Exception('Failed to encrypt value: ' . $e->getMessage());
        }
    }

    public function decryptValue(string $encryptedValue): string
    {
        try {
            return $this->decrypt($encryptedValue);
        } catch (Exception $e) {
            $this->logger->error('Failed to decrypt value', ['error' => $e->getMessage()]);
            throw new Exception('Failed to decrypt value: ' . $e->getMessage());
        }
    }

    public function migrateSecrets(array $configSecrets): array
    {
        try {
            $migrated = 0;
            $skipped = 0;

            foreach ($configSecrets as $key => $value) {
                if (!$this->secretExists($key)) {
                    $this->storeSecret($key, $value, 'Migrated from config');
                    $migrated++;
                } else {
                    $skipped++;
                }
            }

            $this->logger->info('Secret migration completed', [
                'migrated' => $migrated,
                'skipped' => $skipped
            ]);

            return ['migrated' => $migrated, 'skipped' => $skipped];
        } catch (Exception $e) {
            $this->logger->error('Failed to migrate secrets', ['error' => $e->getMessage()]);
            throw new Exception('Failed to migrate secrets: ' . $e->getMessage());
        }
    }

    private function secretExists(string $key): bool
    {
        // Check primary storage
        if (isset($this->secrets[$key]) && !empty($this->secrets[$key]['encrypted_value'])) {
            return true;
        }

        // Check SecretManagerService storage
        try {
            if (class_exists('App\Services\SecretManagerService')) {
                $secretManager = new \App\Services\SecretManagerService($this->logger);
                return $secretManager->secretExists($key);
            }
        } catch (\Exception $e) {
            $this->logger->debug('SecretManager check failed', ['key' => $key, 'error' => $e->getMessage()]);
        }

        return false;
    }

    private function loadSecrets(): void
    {
        try {
            if (file_exists($this->secretsFile)) {
                $content = file_get_contents($this->secretsFile);
                if ($content !== false) {
                    $this->secrets = json_decode($content, true) ?: [];
                }
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to load secrets', ['error' => $e->getMessage()]);
            $this->secrets = [];
        }
    }

    private function saveSecrets(): void
    {
        try {
            $dir = dirname($this->secretsFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $json = json_encode($this->secrets, JSON_PRETTY_PRINT);
            if (file_put_contents($this->secretsFile, $json) === false) {
                throw new Exception('Failed to write secrets file');
            }

            // Secure file permissions
            chmod($this->secretsFile, 0600);
        } catch (Exception $e) {
            $this->logger->error('Failed to save secrets', ['error' => $e->getMessage()]);
            throw new Exception('Failed to save secrets: ' . $e->getMessage());
        }
    }

    private function encrypt(string $data): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($data, $nonce, $this->encryptionKey);
        return base64_encode($nonce . $encrypted);
    }

    private function decrypt(string $encryptedData): string
    {
        $decoded = base64_decode($encryptedData);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $decrypted = sodium_crypto_secretbox_open($encrypted, $nonce, $this->encryptionKey);
        if ($decrypted === false) {
            throw new Exception('Failed to decrypt data');
        }

        return $decrypted;
    }

    private function getOrCreateEncryptionKey(): string
    {
        $keyFile = __DIR__ . '/../../config/.secret_key';

        if (file_exists($keyFile)) {
            $key = file_get_contents($keyFile);
            if ($key !== false && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
                return $key;
            }
        }

        // Generate new key
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        // Ensure directory exists
        $dir = dirname($keyFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($keyFile, $key) === false) {
            throw new Exception('Failed to save encryption key');
        }

        // Secure file permissions
        chmod($keyFile, 0600);

        return $key;
    }
}