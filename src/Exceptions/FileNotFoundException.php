<?php

namespace App\Exceptions;

class FileNotFoundException extends FileAccessException
{
    public function getHttpStatusCode(): int
    {
        return 404;
    }

    public function getErrorCode(): string
    {
        return 'FILE_NOT_FOUND';
    }
}
