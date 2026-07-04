<?php

namespace App\Exceptions;

class FileAccessException extends ToolException
{
    public function getHttpStatusCode(): int
    {
        return 500;
    }

    public function getErrorCode(): string
    {
        return 'FILE_ACCESS_ERROR';
    }
}
