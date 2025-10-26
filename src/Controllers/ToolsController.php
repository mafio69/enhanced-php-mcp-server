<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ErrorResponse;
use App\Interfaces\ToolExecutorInterface;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class ToolsController extends BaseController
{
    private ToolExecutorInterface $toolExecutor;

    public function __construct(
        ServerConfig $config,
        LoggerInterface $logger,
        ToolExecutorInterface $toolExecutor
    ) {
        parent::__construct($config, $logger);
        $this->toolExecutor = $toolExecutor;
    }

    public function listTools(Request $request, Response $response): Response
    {
        $tools = $this->toolExecutor->getTools();

        return $this->jsonResponse($response, $tools);
    }

    public function executeTool(Request $request, Response $response): Response
    {
        $data = $this->getRequestBody($request);
        $toolName = $data['tool'] ?? '';
        $arguments = $data['arguments'] ?? [];

        try {
            $result = $this->toolExecutor->executeTool($toolName, $arguments);

            return $this->successResponse($response, $result);
        } catch (Exception $e) {
            return $this->errorResponse(
                $response,
                new ErrorResponse(
                    'Tool execution failed',
                    500,
                    ['details' => $e->getMessage()]
                )
            );
        }
    }
}
