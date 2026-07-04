<?php

namespace App\Exceptions;

class SecurityException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 403;
    }

    public function getErrorCode(): string
    {
        return 'SECURITY_VIOLATION';
    }
}
