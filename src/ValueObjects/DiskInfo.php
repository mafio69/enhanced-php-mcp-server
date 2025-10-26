<?php

namespace App\ValueObjects;

readonly class DiskInfo
{
    public function __construct(
        public string $total,
        public string $used,
        public string $free,
        public float $percentage_used
    ) {}

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'used' => $this->used,
            'free' => $this->free,
            'percentage_used' => $this->percentage_used,
        ];
    }
}