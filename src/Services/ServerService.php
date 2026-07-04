<?php

namespace App\Services;

use App\Config\ServerConfig;

class ServerService
{
    private ServerConfig $config;

    public function __construct(ServerConfig $config)
    {
        $this->config = $config;
    }

    public function addServer(array $serverData): array
    {
        $serverName = $serverData['name'];
        $serverConfig = $serverData['config'];

        $this->config->addMcpServer($serverName, $serverConfig);

        return [
            'name' => $serverName,
            'config' => $serverConfig,
        ];
    }

    public function getServers(): array
    {
        return $this->config->getMcpServers();
    }

    public function deleteServer(string $name): void
    {
        $this->config->deleteMcpServer($name);
    }
}
