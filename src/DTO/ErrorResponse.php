<?php

namespace App\DTO;

class ErrorResponse
{
    private bool $success;
    private string $error;
    private int $code;
    private array $details;
    private string $timestamp;

    public function __construct(string $error, int $code = 400, array $details = [])
    {
        $this->success = false;
        $this->error = $error;
        $this->code = $code;
        $this->details = $details;
        $this->timestamp = date('Y-m-d H:i:s');
    }

    public static function badRequest(string $message, array $details = []): self
    {
        return new self($message, 400, $details);
    }

    public static function notFound(string $message = 'Resource not found', array $details = []): self
    {
        return new self($message, 404, $details);
    }

    public static function forbidden(string $message = 'Access denied', array $details = []): self
    {
        return new self($message, 403, $details);
    }

    public static function internalError(string $message = 'Internal server error', array $details = []): self
    {
        return new self($message, 500, $details);
    }

    public static function validationFailed(array $errors, string $message = 'Validation failed'): self
    {
        return new self($message, 422, ['validation_errors' => $errors]);
    }

    public static function toolError(string $tool, string $error, array $arguments = []): self
    {
        return new self("Tool '$tool' failed: $error", 500, [
            'tool' => $tool,
            'arguments' => $arguments,
        ]);
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'error' => $this->error,
            'code' => $this->code,
            'timestamp' => $this->timestamp,
            'details' => $this->details,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}