<?php

namespace App\Exceptions;

class DirectoryNotFoundException extends FileAccessException
{
    public function getHttpStatusCode(): int
    {
        return 404;
    }

    public function getErrorCode(): string
    {
        return 'DIRECTORY_NOT_FOUND';
    }
}
