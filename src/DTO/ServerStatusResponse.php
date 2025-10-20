<?php

namespace App\DTO;

class ServerStatusResponse
{
    private string $status;
    private ServerInfo $server;
    private array $metrics;
    private array $tools;
    private string $timestamp;

    public function __construct(
        string $status,
        ServerInfo $server,
        array $metrics = [],
        array $tools = []
    ) {
        $this->status = $status;
        $this->server = $server;
        $this->metrics = $metrics;
        $this->tools = $tools;
        $this->timestamp = date('Y-m-d H:i:s');
    }

    public static function running(ServerInfo $server, array $metrics = [], array $tools = []): self
    {
        return new self('running', $server, $metrics, $tools);
    }

    public static function stopped(string $reason = ''): self
    {
        $server = new ServerInfo('stopped', '0.0.0', 'Stopped', $reason);
        return new self('stopped', $server);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getServer(): ServerInfo
    {
        return $this->server;
    }

    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function getTools(): array
    {
        return $this->tools;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'server' => $this->server->toArray(),
            'metrics' => $this->metrics,
            'tools' => $this->tools,
            'timestamp' => $this->timestamp
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}