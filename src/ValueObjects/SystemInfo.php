<?php

namespace App\ValueObjects;

readonly class SystemInfo
{
    public function __construct(
        public array $platform,
        public array $php,
        public array $server,
        public array $resources,
        public array $security
    ) {}

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'php' => $this->php,
            'server' => $this->server,
            'resources' => [
                'memory' => $this->resources['memory']->toArray(),
                'disk' => $this->resources['disk']->toArray(),
                'load_average' => $this->resources['load_average'] ?? null,
                'processes' => $this->resources['processes'] ?? null,
            ],
            'security' => $this->security,
        ];
    }
}