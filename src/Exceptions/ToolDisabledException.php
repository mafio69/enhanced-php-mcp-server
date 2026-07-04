<?php

namespace App\Exceptions;

class ToolDisabledException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 403;
    }

    public function getErrorCode(): string
    {
        return 'TOOL_DISABLED';
    }
}
