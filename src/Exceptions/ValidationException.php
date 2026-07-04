<?php

namespace App\Exceptions;

class ValidationException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 400;
    }

    public function getErrorCode(): string
    {
        return 'VALIDATION_FAILED';
    }
}
