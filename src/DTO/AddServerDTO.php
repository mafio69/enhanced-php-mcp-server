<?php

namespace App\DTO;

class AddServerDTO
{
    public string $name;
    public array $config;

    public function __construct(array $data)
    {
        $this->name = $data['name'] ?? '';
        $jsonConfig = $data['json_config'] ?? '';
        $this->config = json_decode($jsonConfig, true) ?? [];
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->name)) {
            $errors['name'] = 'Name is required';
        }

        if (empty($this->config)) {
            $errors['json_config'] = 'Invalid or empty JSON configuration';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'config' => $this->config
        ];
    }
}
