<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ErrorResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

abstract class BaseController
{
    protected ServerConfig $config;
    protected LoggerInterface $logger;

    public function __construct(ServerConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    protected function jsonResponse(Response $response, $data, int $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    protected function errorResponse(Response $response, ErrorResponse $error): Response
    {
        $this->logger->error('API Error', [
            'error' => $error->getError(),
            'code' => $error->getCode(),
            'details' => $error->getDetails(),
        ]);

        return $this->jsonResponse($response, $error->toArray(), $error->getCode());
    }

    protected function successResponse(Response $response, $data = null): Response
    {
        $responseData = [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($data !== null) {
            $responseData['data'] = $data;
        }

        return $this->jsonResponse($response, $responseData);
    }

    protected function getRequestBody(Request $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (strpos($contentType, 'application/json') !== false) {
            return (array)json_decode($request->getBody()->getContents(), true) ?: [];
        }

        return [];
    }

    protected function validateRequiredFields(array $data, array $requiredFields): array
    {
        $missing = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    protected function measureExecutionTime(callable $callback): array
    {
        $startTime = microtime(true);
        $result = $callback();
        $executionTime = microtime(true) - $startTime;

        return ['result' => $result, 'execution_time' => $executionTime];
    }
}