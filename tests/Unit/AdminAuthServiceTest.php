<?php

namespace Tests\Unit;

use App\Services\AdminAuthService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AdminAuthServiceTest extends TestCase
{
    private LoggerInterface $loggerMock;
    private string $tempSessionPath;
    private string $tempPasswordFile;
    private AdminAuthService $service;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->tempSessionPath = sys_get_temp_dir() . '/session_test_' . uniqid();
        $this->tempPasswordFile = sys_get_temp_dir() . '/password_test_' . uniqid() . '.txt';

        $this->service = new AdminAuthService(
            $this->loggerMock,
            'admin_user',
            'admin_pass',
            $this->tempSessionPath,
            $this->tempPasswordFile
        );
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPasswordFile)) {
            unlink($this->tempPasswordFile);
        }
        if (is_dir($this->tempSessionPath)) {
            $this->removeDirectory($this->tempSessionPath);
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testAuthenticateSuccess(): void
    {
        $this->assertTrue($this->service->authenticate('admin_user', 'admin_pass'));
    }

    public function testAuthenticateFailureInvalidUser(): void
    {
        $this->assertFalse($this->service->authenticate('wrong_user', 'admin_pass'));
    }

    public function testAuthenticateFailureInvalidPassword(): void
    {
        $this->assertFalse($this->service->authenticate('admin_user', 'wrong_pass'));
    }

    public function testPasswordChangeIsPersistent(): void
    {
        // Change the password
        $this->assertTrue($this->service->changePassword('admin_pass', 'new_secure_pass'));
        
        // Verify it works in current instance
        $this->assertTrue($this->service->authenticate('admin_user', 'new_secure_pass'));
        $this->assertFalse($this->service->authenticate('admin_user', 'admin_pass'));

        // Instantiate new service using the same tempPasswordFile to check persistence
        $newService = new AdminAuthService(
            $this->loggerMock,
            'admin_user',
            null, // should load hash from file instead of generating from env/default
            $this->tempSessionPath,
            $this->tempPasswordFile
        );

        // Verify the new instance uses the changed password
        $this->assertTrue($newService->authenticate('admin_user', 'new_secure_pass'));
        $this->assertFalse($newService->authenticate('admin_user', 'admin_pass'));
    }
}
