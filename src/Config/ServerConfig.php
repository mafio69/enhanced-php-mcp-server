<?php

namespace App\Config;

class ServerConfig
{
    private array $config;
    private string $configFile;

    public function __construct(?array $config = null)
    {
        $this->configFile = __DIR__.'/../../config/server.php';
        $this->config = $config ?? require $this->configFile;
    }

    public function addMcpServer(string $name, array $serverConfig): void
    {
        if (!isset($this->config['mcpServers'])) {
            $this->config['mcpServers'] = [];
        }
        $this->config['mcpServers'][$name] = $serverConfig;
        $this->saveConfig();
    }

    private function saveConfig(): void
    {
        $configAsString = "<?php\n\nreturn ".var_export($this->config, true).";\n";
        file_put_contents($this->configFile, $configAsString);
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($this->configFile, true);
        }
    }

    public function getName(): string
    {
        return $this->config['server']['name'] ?? 'enhanced-php-mcp-server';
    }

    public function getVersion(): string
    {
        return $this->config['server']['version'] ?? '2.1.0';
    }

    public function getDescription(): string
    {
        return $this->config['server']['description'] ?? 'Professional PHP MCP Server';
    }

    public function isDebugMode(): bool
    {
        return $this->config['debug'] ?? false;
    }

    public function getLoggingConfig(): array
    {
        return $this->config['logging'] ?? [];
    }

    public function isLoggingEnabled(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }

    public function getLogFile(): string
    {
        return $this->config['logging']['file'] ?? __DIR__.'/../../logs/server.log';
    }

    public function getLogLevel(): string
    {
        return $this->config['logging']['level'] ?? 'info';
    }

    public function getMaxLogFiles(): int
    {
        return $this->config['logging']['max_files'] ?? 5;
    }

    public function getAllowedPaths(): array
    {
        return $this->config['security']['allowed_paths'] ?? [__DIR__.'/../..'];
    }

    public function getMaxFileSize(): int
    {
        return $this->config['security']['max_file_size'] ?? 10485760; // 10MB
    }

    public function getHttpConfig(): array
    {
        return $this->config['http'] ?? [];
    }

    public function getHttpTimeout(): int
    {
        return $this->config['http']['timeout'] ?? 10;
    }

    public function getHttpUserAgent(): string
    {
        return $this->config['http']['user_agent'] ?? 'PHP-MCP-Server/2.1.0';
    }

    public function areHttpRedirectsAllowed(): bool
    {
        return $this->config['http']['allow_redirects'] ?? true;
    }

    public function getEnabledTools(): array
    {
        return $this->config['tools']['enabled'] ?? [
            'hello',
            'get_time',
            'calculate',
            'read_file',
            'write_file',
            'list_files',
            'system_info',
            'http_request',
            'json_parse',
            'get_weather',
        ];
    }

    public function getRestrictedTools(): array
    {
        return $this->config['tools']['restricted'] ?? ['write_file', 'http_request'];
    }

    public function isToolEnabled(string $toolName): bool
    {
        return in_array($toolName, $this->getEnabledTools());
    }

    public function isToolRestricted(string $toolName): bool
    {
        return in_array($toolName, $this->getRestrictedTools());
    }

    public function getMcpServers(): array
    {
        return $this->config['mcpServers'] ?? [];
    }

    public function getFullConfig(): array
    {
        return $this->config;
    }
}
