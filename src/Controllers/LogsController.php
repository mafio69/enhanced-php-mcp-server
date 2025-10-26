<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LogsController extends BaseController
{
    public function getLogs(Request $request, Response $response): Response
    {
        $logFile = $this->config->getLogFile();
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLines = array_slice($lines, -50);
            $data = ['logs' => $recentLines];
        } else {
            $data = ['logs' => [], 'message' => 'Log file not found'];
        }

        return $this->jsonResponse($response, $data);
    }

    public function clearLogs(Request $request, Response $response): Response
    {
        // Implementacja do dodania
        return $this->jsonResponse($response, ['message' => 'Logs cleared']);
    }

    public function downloadLogs(Request $request, Response $response): Response
    {
        // Implementacja do dodania
        return $this->jsonResponse($response, ['message' => 'Logs downloaded']);
    }

    public function getLogStats(Request $request, Response $response): Response
    {
        // Implementacja do dodania
        return $this->jsonResponse($response, ['message' => 'Log stats']);
    }
}
