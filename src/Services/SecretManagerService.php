<?php

namespace App\Services;

use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SecretManagerService
{
    private LoggerInterface $logger;
    private string $secretsPath;
    private string $encryptionKey;

    public function __construct(LoggerInterface $logger, ?string $secretsPath = null, ?string $encryptionKey = null)
    {
        $this->logger = $logger;
        $this->secretsPath = $secretsPath ?? __DIR__.'/../../storage/secrets';
        $this->encryptionKey = $encryptionKey ?? $this->getEncryptionKey();

        $this->initializeStorage();
    }

    /**
     * Initialize secure storage directory
     */
    private function initializeStorage(): void
    {
        if (!is_dir($this->secretsPath)) {
            if (!mkdir($this->secretsPath, 0700, true)) {
                throw new RuntimeException("Cannot create secrets directory: {$this->secretsPath}");
            }
            $this->logger->info("Created secrets directory", ['path' => $this->secretsPath]);
        }

        // Set restrictive permissions
        chmod($this->secretsPath, 0700);
    }

    /**
     * Get encryption key from environment or generate a new one
     */
    private function getEncryptionKey(): string
    {
        // Try to get from environment first
        $key = $_ENV['MCP_SECRET_KEY'] ?? $_SERVER['MCP_SECRET_KEY'] ?? null;

        if ($key) {
            $this->logger->debug("Using encryption key from environment");

            return $key;
        }

        // Try to load from key file
        $keyFile = __DIR__.'/../../storage/.secret_key';
        if (file_exists($keyFile)) {
            $key = file_get_contents($keyFile);
            if ($key !== false) {
                $this->logger->debug("Using encryption key from file");

                return trim($key);
            }
        }

        // Generate new key if none exists
        $key = $this->generateEncryptionKey();
        $this->saveEncryptionKey($key);
        $this->logger->info("Generated new encryption key");

        return $key;
    }

    /**
     * Generate a secure encryption key
     */
    private function generateEncryptionKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    /**
     * Save encryption key to secure file
     */
    private function saveEncryptionKey(string $key): void
    {
        $keyFile = __DIR__.'/../../storage/.secret_key';
        $keyDir = dirname($keyFile);

        if (!is_dir($keyDir)) {
            mkdir($keyDir, 0700, true);
        }

        if (file_put_contents($keyFile, $key) === false) {
            throw new RuntimeException("Cannot save encryption key to: {$keyFile}");
        }

        // Restrict file permissions
        chmod($keyFile, 0600);
        chmod($keyDir, 0700);
    }

    /**
     * Encrypt sensitive data
     */
    public function encrypt(string $data): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', base64_decode($this->encryptionKey), 0, $iv);

        if ($encrypted === false) {
            throw new RuntimeException("Failed to encrypt data");
        }

        return base64_encode($iv.$encrypted);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt(string $encryptedData): string
    {
        $data = base64_decode($encryptedData);
        if ($data === false || strlen($data) < 16) {
            throw new RuntimeException("Invalid encrypted data format");
        }

        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', base64_decode($this->encryptionKey), 0, $iv);

        if ($decrypted === false) {
            throw new RuntimeException("Failed to decrypt data");
        }

        return $decrypted;
    }

    /**
     * Store a secret securely
     */
    public function storeSecret(string $key, string $value): void
    {
        $encryptedValue = $this->encrypt($value);
        $filePath = $this->secretsPath.'/'.$this->sanitizeKey($key).'.sec';

        if (file_put_contents($filePath, $encryptedValue) === false) {
            throw new RuntimeException("Failed to store secret: {$key}");
        }

        chmod($filePath, 0600);
        $this->logger->info("Secret stored", ['key' => $key]);
    }

    /**
     * Retrieve a secret
     */
    public function getSecret(string $key): ?string
    {
        $filePath = $this->secretsPath.'/'.$this->sanitizeKey($key).'.sec';

        if (!file_exists($filePath)) {
            return null;
        }

        $encryptedValue = file_get_contents($filePath);
        if ($encryptedValue === false) {
            throw new RuntimeException("Failed to read secret: {$key}");
        }

        try {
            $decryptedValue = $this->decrypt($encryptedValue);
            $this->logger->debug("Secret retrieved", ['key' => $key]);

            return $decryptedValue;
        } catch (Exception $e) {
            $this->logger->error("Failed to decrypt secret", ['key' => $key, 'error' => $e->getMessage()]);
            throw new RuntimeException("Failed to decrypt secret: {$key}");
        }
    }

    /**
     * Delete a secret
     */
    public function deleteSecret(string $key): bool
    {
        $filePath = $this->secretsPath.'/'.$this->sanitizeKey($key).'.sec';

        if (file_exists($filePath)) {
            if (unlink($filePath)) {
                $this->logger->info("Secret deleted", ['key' => $key]);

                return true;
            }
        }

        return false;
    }

    /**
     * List all stored secret keys (without values)
     */
    public function listSecrets(): array
    {
        $secrets = [];
        $files = glob($this->secretsPath.'/*.sec');

        if ($files !== false) {
            foreach ($files as $file) {
                $key = basename($file, '.sec');
                $secrets[] = $this->unsanitizeKey($key);
            }
        }

        sort($secrets);

        return $secrets;
    }

    /**
     * Check if secret exists
     */
    public function secretExists(string $key): bool
    {
        $filePath = $this->secretsPath.'/'.$this->sanitizeKey($key).'.sec';

        return file_exists($filePath);
    }

    /**
     * Sanitize key for filename
     */
    private function sanitizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
    }

    /**
     * Unsanitize key from filename
     */
    private function unsanitizeKey(string $key): string
    {
        return preg_replace('/_+/', '.', $key);
    }

    /**
     * Process configuration and encrypt secrets
     */
    public function processConfiguration(array $config): array
    {
        foreach ($config as &$value) {
            if (is_array($value)) {
                $value = $this->processConfiguration($value);
            } elseif (is_string($value) && $this->isSecret($value)) {
                // Check if it's already encrypted
                if (!$this->isEncrypted($value)) {
                    $value = $this->encrypt($value);
                }
            }
        }

        return $config;
    }

    /**
     * Process configuration and decrypt secrets
     */
    public function decryptConfiguration(array $config): array
    {
        foreach ($config as &$value) {
            if (is_array($value)) {
                $value = $this->decryptConfiguration($value);
            } elseif (is_string($value) && $this->isEncrypted($value)) {
                try {
                    $value = $this->decrypt($value);
                } catch (Exception $e) {
                    $this->logger->warning("Failed to decrypt configuration value", ['error' => $e->getMessage()]);
                    // Keep original value if decryption fails
                }
            }
        }

        return $config;
    }

    /**
     * Check if a string looks like a secret
     */
    private function isSecret(string $value): bool
    {
        // Check for common secret patterns
        $secretPatterns = [
            '/^sk-/', // OpenAI API keys
            '/^AIza[0-9A-Za-z_-]{35}$/', // Google API keys
            '/^[A-Za-z0-9]{32,}$/', // Generic long keys
            '/^BRAVE_API_KEY/',
            '/^[A-Za-z0-9_-]{20,}$/', // Generic API keys
        ];

        foreach ($secretPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a string is encrypted
     */
    private function isEncrypted(string $value): bool
    {
        $decoded = base64_decode($value);
        if ($decoded === false) {
            return false;
        }

        // Encrypted data should be at least 16 bytes (IV) + some data
        return strlen($decoded) >= 20;
    }
}