<?php

namespace App\Exceptions;

class JsonParseException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 400;
    }

    public function getErrorCode(): string
    {
        return 'JSON_PARSE_ERROR';
    }
}
