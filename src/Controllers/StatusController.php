<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\Services\MonitoringService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class StatusController extends BaseController
{
    private MonitoringService $monitoring;

    public function __construct(ServerConfig $config, LoggerInterface $logger, MonitoringService $monitoring)
    {
        parent::__construct($config, $logger);
        $this->monitoring = $monitoring;
    }

    public function getServerStatus(Request $request, Response $response): Response
    {
        $data = [
            'status' => 'running',
            'server' => [
                'name' => $this->config->getName(),
                'version' => $this->config->getVersion(),
            ],
            'metrics' => $this->monitoring->getMetrics()
        ];
        return $this->jsonResponse($response, $data);
    }

    public function getMetrics(Request $request, Response $response): Response
    {
        return $this->jsonResponse($response, $this->monitoring->getMetrics());
    }

    public function getHealth(Request $request, Response $response): Response
    {
        // Prosta implementacja, można rozbudować
        $data = ['status' => 'healthy'];
        return $this->jsonResponse($response, $data);
    }
}
