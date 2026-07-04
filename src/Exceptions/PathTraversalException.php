<?php

namespace App\Exceptions;

class PathTraversalException extends SecurityException
{
    public function getErrorCode(): string
    {
        return 'PATH_TRAVERSAL_DETECTED';
    }
}
