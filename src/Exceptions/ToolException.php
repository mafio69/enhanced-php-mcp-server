<?php

namespace App\Exceptions;

use Exception;

abstract class ToolException extends Exception
{
    abstract public function getHttpStatusCode(): int;
    abstract public function getErrorCode(): string;
}
