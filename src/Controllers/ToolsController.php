<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\ErrorResponse;
use App\MCPServerHTTP;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class ToolsController extends BaseController
{
    private MCPServerHTTP $httpServer;

    // MCPServerHTTP jest potrzebny do pobrania listy narzędzi i ich wykonania
    public function __construct(
        ServerConfig $config,
        LoggerInterface $logger,
        MCPServerHTTP $httpServer
    ) {
        parent::__construct($config, $logger);
        $this->httpServer = $httpServer;
    }

    public function listTools(Request $request, Response $response): Response
    {
        $tools = $this->httpServer->getTools();

        return $this->jsonResponse($response, ['tools' => $tools]);
    }

    public function executeTool(Request $request, Response $response): Response
    {
        $data = $this->getRequestBody($request);
        $toolName = $data['tool'] ?? '';
        $arguments = $data['arguments'] ?? [];

        try {
            $result = $this->httpServer->executeTool($toolName, $arguments);

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
