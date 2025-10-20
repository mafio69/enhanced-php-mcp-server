<?php

namespace App\DTO;

class ToolRequest
{
    private string $tool;
    private array $arguments;

    public function __construct(string $tool, array $arguments = [])
    {
        $this->tool = $tool;
        $this->arguments = $arguments;
    }

    public function getTool(): string
    {
        return $this->tool;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $key, $default = null)
    {
        return $this->arguments[$key] ?? $default;
    }

    public function hasArgument(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    public function setArgument(string $key, $value): self
    {
        $this->arguments[$key] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'arguments' => $this->arguments
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['tool'] ?? '',
            $data['arguments'] ?? []
        );
    }

    public static function fromRequestBody(array $body): self
    {
        return new self(
            $body['tool'] ?? $body['name'] ?? '', // Support both formats
            $body['arguments'] ?? $body['params'] ?? [] // Support both formats
        );
    }
}