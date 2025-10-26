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
}
