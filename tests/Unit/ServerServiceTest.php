<?php

namespace Tests\Unit;

use App\Config\ServerConfig;
use App\Services\ServerService;
use PHPUnit\Framework\TestCase;

class ServerServiceTest extends TestCase
{
    private ServerService $service;
    private ServerConfig $config;
    private string $tempConfigFile;

    protected function setUp(): void
    {
        $this->tempConfigFile = sys_get_temp_dir() . '/server_test_' . uniqid() . '.php';
        
        // Write temporary config file with basic mock structure
        $mockConfig = [
            'mcpServers' => [
                'existing-server' => [
                    'command' => 'echo',
                    'args' => ['test'],
                ],
            ],
        ];
        file_put_contents($this->tempConfigFile, "<?php\nreturn " . var_export($mockConfig, true) . ";");

        // Instantiate ServerConfig with config array
        $this->config = new ServerConfig($mockConfig);
        
        // Set private property configFile via reflection to use temp file
        $reflection = new \ReflectionClass(ServerConfig::class);
        $property = $reflection->getProperty('configFile');
        $property->setValue($this->config, $this->tempConfigFile);

        $this->service = new ServerService($this->config);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfigFile)) {
            unlink($this->tempConfigFile);
        }
    }

    public function testGetServers(): void
    {
        $servers = $this->service->getServers();
        $this->assertArrayHasKey('existing-server', $servers);
        $this->assertSame('echo', $servers['existing-server']['command']);
    }

    public function testAddServer(): void
    {
        $serverData = [
            'name' => 'new-server',
            'config' => [
                'command' => 'node',
                'args' => ['index.js'],
            ],
        ];

        $result = $this->service->addServer($serverData);
        $this->assertSame('new-server', $result['name']);
        
        $servers = $this->service->getServers();
        $this->assertArrayHasKey('new-server', $servers);
        $this->assertSame('node', $servers['new-server']['command']);
    }

    public function testDeleteServer(): void
    {
        $this->service->deleteServer('existing-server');
        
        $servers = $this->service->getServers();
        $this->assertArrayNotHasKey('existing-server', $servers);
    }
}
