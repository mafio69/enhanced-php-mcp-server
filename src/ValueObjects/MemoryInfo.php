<?php

namespace App\ValueObjects;

readonly class MemoryInfo
{
    public function __construct(
        public string $current,
        public string $peak,
        public string $limit
    ) {}

    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'peak' => $this->peak,
            'limit' => $this->limit,
        ];
    }
}