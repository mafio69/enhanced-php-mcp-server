<?php

namespace Tests\Unit;

use App\Services\SecretManagerService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class SecretManagerServiceTest extends TestCase
{
    private SecretManagerService $service;
    private LoggerInterface $loggerMock;
    private string $tempDir;
    private string $encryptionKey;

    public function testEncryptDecryptRoundtrip(): void
    {
        $original = 'super-secret-api-key-12345';
        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertSame($original, $decrypted);
    }

    public function testEncryptProducesDifferentOutputEachTime(): void
    {
        $value = 'test-value';
        $result1 = $this->service->encrypt($value);
        $result2 = $this->service->encrypt($value);
        $this->assertNotSame($result1, $result2);
    }

    public function testDecryptWithInvalidDataThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid encrypted data format');
        $this->service->decrypt('too-short');
    }

    public function testStoreAndGetSecret(): void
    {
        $this->service->storeSecret('test.key', 'my-secret-value');
        $result = $this->service->getSecret('test.key');
        $this->assertSame('my-secret-value', $result);
    }

    public function testGetNonExistentSecretReturnsNull(): void
    {
        $result = $this->service->getSecret('non.existent');
        $this->assertNull($result);
    }

    public function testStoreOverwritesExistingSecret(): void
    {
        $this->service->storeSecret('test.key', 'first-value');
        $this->service->storeSecret('test.key', 'second-value');
        $result = $this->service->getSecret('test.key');
        $this->assertSame('second-value', $result);
    }

    public function testDeleteExistingSecretReturnsTrue(): void
    {
        $this->service->storeSecret('delete.me', 'value');
        $result = $this->service->deleteSecret('delete.me');
        $this->assertTrue($result);
        $this->assertNull($this->service->getSecret('delete.me'));
    }

    public function testDeleteNonExistentSecretReturnsFalse(): void
    {
        $result = $this->service->deleteSecret('never.stored');
        $this->assertFalse($result);
    }

    public function testListSecretsReturnsEmptyArrayWhenNoSecrets(): void
    {
        $secrets = $this->service->listSecrets();
        $this->assertIsArray($secrets);
        $this->assertCount(0, $secrets);
    }

    public function testListSecretsReturnsStoredKeys(): void
    {
        $this->service->storeSecret('key.one', 'value1');
        $this->service->storeSecret('key.two', 'value2');
        $this->service->storeSecret('key.three', 'value3');

        $secrets = $this->service->listSecrets();
        $this->assertCount(3, $secrets);
        $this->assertContains('key.one', $secrets);
        $this->assertContains('key.two', $secrets);
        $this->assertContains('key.three', $secrets);
    }

    public function testSecretExists(): void
    {
        $this->assertFalse($this->service->secretExists('not.stored'));
        $this->service->storeSecret('exists.test', 'value');
        $this->assertTrue($this->service->secretExists('exists.test'));
    }

    public function testSanitizeKeyWithSpecialCharacters(): void
    {
        $key = 'my/key@special!chars#';
        $this->service->storeSecret($key, 'value');
        $this->assertTrue($this->service->secretExists($key));
        $this->assertSame('value', $this->service->getSecret($key));
    }

    public function testProcessConfigurationEncryptsSecrets(): void
    {
        $config = [
            'api_key' => 'sk-'.str_repeat('a', 40),
            'normal' => 'just a string',
        ];

        $processed = $this->service->processConfiguration($config);

        $this->assertNotSame($config['api_key'], $processed['api_key']);
        $this->assertSame($config['normal'], $processed['normal']);
    }

    public function testDecryptConfigurationRestoresValues(): void
    {
        $config = [
            'api_key' => 'sk-'.str_repeat('a', 40),
            'normal' => 'just a string',
        ];

        $processed = $this->service->processConfiguration($config);
        $decrypted = $this->service->decryptConfiguration($processed);

        $this->assertSame($config['api_key'], $decrypted['api_key']);
        $this->assertSame($config['normal'], $decrypted['normal']);
    }

    public function testEncryptedDataIsBase64Encoded(): void
    {
        $encrypted = $this->service->encrypt('test');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]+=*$/', $encrypted);
    }

    public function testGetSecretWithCorruptedFileThrowsException(): void
    {
        $this->service->storeSecret('corrupt', 'value');
        $filePath = $this->tempDir.'/corrupt.sec';
        file_put_contents($filePath, 'not-valid-base64-encoded-data');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to decrypt secret: corrupt');

        $this->service->getSecret('corrupt');
    }

    protected function setUp(): void
    {
        $this->encryptionKey = base64_encode(str_repeat('a', 32));
        $this->tempDir = sys_get_temp_dir().'/secret_test_'.uniqid();

        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->service = new SecretManagerService(
            $this->loggerMock,
            $this->tempDir,
            $this->encryptionKey
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir.'/'.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
