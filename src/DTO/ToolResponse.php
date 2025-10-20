<?php

namespace App\DTO;

class ToolResponse
{
    private bool $success;
    private string $tool;
    private $result;
    private array $arguments;
    private string $error;
    private float $executionTime;
    private string $timestamp;

    public function __construct(
        string $tool,
        bool $success = true,
        $result = null,
        array $arguments = [],
        string $error = '',
        float $executionTime = 0.0
    ) {
        $this->tool = $tool;
        $this->success = $success;
        $this->result = $result;
        $this->arguments = $arguments;
        $this->error = $error;
        $this->executionTime = $executionTime;
        $this->timestamp = date('Y-m-d H:i:s');
    }

    public static function success(string $tool, $result, array $arguments = [], float $executionTime = 0.0): self
    {
        return new self($tool, true, $result, $arguments, '', $executionTime);
    }

    public static function error(string $tool, string $error, array $arguments = [], float $executionTime = 0.0): self
    {
        return new self($tool, false, null, $arguments, $error, $executionTime);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getTool(): string
    {
        return $this->tool;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getExecutionTime(): float
    {
        return $this->executionTime;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        $data = [
            'success' => $this->success,
            'tool' => $this->tool,
            'timestamp' => $this->timestamp,
            'execution_time' => round($this->executionTime, 3)
        ];

        if ($this->success) {
            $data['result'] = $this->result;
            if (!empty($this->arguments)) {
                $data['arguments'] = $this->arguments;
            }
        } else {
            $data['error'] = $this->error;
            if (!empty($this->arguments)) {
                $data['arguments'] = $this->arguments;
            }
        }

        return $data;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}