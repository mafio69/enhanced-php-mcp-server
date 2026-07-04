<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\DTO\AddServerDTO;
use App\DTO\ErrorResponse;
use App\Services\ServerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class ServerController extends BaseController
{
    private ServerService $serverService;

    public function __construct(ServerService $serverService, ServerConfig $config, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);
        $this->serverService = $serverService;
    }

    public function addServer(Request $request, Response $response): Response
    {
        $data = $this->getRequestBody($request);
        $dto = new AddServerDTO($data);
        $errors = $dto->validate();

        if (!empty($errors)) {
            return $this->errorResponse(
                $response,
                new ErrorResponse(
                    'Invalid input',
                    400,
                    $errors
                )
            );
        }

        $server = $this->serverService->addServer($dto->toArray());

        return $this->successResponse($response, $server);
    }

    public function listServers(Request $request, Response $response): Response
    {
        try {
            $servers = $this->serverService->getServers();
            return $this->successResponse($response, $servers);
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                new ErrorResponse(
                    'Failed to retrieve servers: ' . $e->getMessage(),
                    500
                )
            );
        }
    }

    public function deleteServer(Request $request, Response $response, array $args): Response
    {
        $name = $args['name'] ?? '';

        if (empty($name)) {
            return $this->errorResponse(
                $response,
                new ErrorResponse(
                    'Server name is required',
                    400
                )
            );
        }

        try {
            $this->serverService->deleteServer($name);
            return $this->successResponse($response, ['message' => 'Server deleted successfully']);
        } catch (\Exception $e) {
            return $this->errorResponse(
                $response,
                new ErrorResponse(
                    'Failed to delete server: ' . $e->getMessage(),
                    500
                )
            );
        }
    }
}
