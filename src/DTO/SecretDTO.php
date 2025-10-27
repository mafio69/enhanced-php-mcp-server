<?php

namespace App\DTO;

class SecretDTO
{
    private string $key;
    private string $value;
    private string $description;

    public function __construct(string $key, string $value, string $description = '')
    {
        $this->key = $key;
        $this->value = $value;
        $this->description = $description;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->value,
            'description' => $this->description,
        ];
    }
}