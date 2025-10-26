<?php

namespace App\DTO;

class ServerInfo
{
    private string $name;
    private string $version;
    private string $description;
    private string $status;
    private array $details;

    public function __construct(
        string $name,
        string $version,
        string $description,
        string $status = 'running',
        array $details = []
    ) {
        $this->name = $name;
        $this->version = $version;
        $this->description = $description;
        $this->status = $status;
        $this->details = $details;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function setDetail(string $key, $value): self
    {
        $this->details[$key] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'description' => $this->description,
            'status' => $this->status,
            'details' => $this->details,
        ];
    }
}