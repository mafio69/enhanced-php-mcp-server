<?php

namespace App\Exceptions;

class ToolNotFoundException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 404;
    }

    public function getErrorCode(): string
    {
        return 'TOOL_NOT_FOUND';
    }
}
