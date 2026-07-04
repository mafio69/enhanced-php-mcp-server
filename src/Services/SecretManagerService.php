<?php

namespace App\Services;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class SecretManagerService
{
    public function __construct(
        private LoggerInterface $logger,
        private string $storagePath,
        private string $encryptionKey
    ) {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }
    }

    public function storeSecret(string $key, string $value): void
    {
        $encrypted = $this->encrypt($value);
        $file = $this->getFilePath($key);
        if (file_put_contents($file, $encrypted) === false) {
            throw new RuntimeException("Write failed");
        }
    }

    public function encrypt(string $value): string
    {
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');
        $iv = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', base64_decode($this->encryptionKey), 0, $iv);

        return base64_encode($iv.'::'.$encrypted);
    }

    private function getFilePath(string $key): string
    {
        return rtrim($this->storagePath, '/').'/'.urlencode($key).'.sec';
    }

    public function getSecret(string $key): ?string
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $encrypted = file_get_contents($file);
        try {
            return $this->decrypt($encrypted);
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to decrypt secret: $key", 0, $e);
        }
    }

    public function decrypt(string $encrypted): string
    {
        $data = base64_decode($encrypted, true);
        if ($data === false || !str_contains($data, '::')) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        $parts = explode('::', $data, 2);
        if (count($parts) !== 2) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        [$iv, $ciphertext] = $parts;
        $ivLength = openssl_cipher_iv_length('aes-256-cbc');

        if (strlen($iv) !== $ivLength) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', base64_decode($this->encryptionKey), 0, $iv);

        if ($decrypted === false) {
            throw new RuntimeException('Failed to decrypt data');
        }

        return $decrypted;
    }

    public function deleteSecret(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    public function listSecrets(): array
    {
        $secrets = [];
        if (!is_dir($this->storagePath)) {
            return $secrets;
        }

        $files = scandir($this->storagePath);
        foreach ($files as $file) {
            if (str_ends_with($file, '.sec')) {
                $secrets[] = urldecode(substr($file, 0, -4));
            }
        }

        return $secrets;
    }

    public function secretExists(string $key): bool
    {
        return file_exists($this->getFilePath($key));
    }

    public function processConfiguration(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_string($value) && str_starts_with($value, 'sk-')) {
                $config[$key] = $this->encrypt($value);
            }
        }

        return $config;
    }

    public function decryptConfiguration(array $config): array
    {
        foreach ($config as $key => $value) {
            if (is_string($value) && $this->isEncryptedFormat($value)) {
                try {
                    $config[$key] = $this->decrypt($value);
                } catch (Throwable) {
                    // skip if fails
                }
            }
        }

        return $config;
    }

    private function isEncryptedFormat(string $value): bool
    {
        if (!preg_match('/^[A-Za-z0-9+\/]+=*$/', $value)) {
            return false;
        }
        $decoded = base64_decode($value, true);

        return $decoded !== false && str_contains($decoded, '::');
    }
}
