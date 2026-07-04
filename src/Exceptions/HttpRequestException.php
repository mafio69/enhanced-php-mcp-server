<?php

namespace App\Exceptions;

class HttpRequestException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 502;
    }

    public function getErrorCode(): string
    {
        return 'HTTP_REQUEST_FAILED';
    }
}
