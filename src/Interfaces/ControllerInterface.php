<?php

namespace App\Interfaces;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Interface for HTTP controllers
 *
 * Defines standard controller contract.
 */
interface ControllerInterface
{
    /**
     * Handle HTTP request
     */
    public function handle(Request $request, Response $response): Response;
}