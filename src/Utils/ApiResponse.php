<?php

namespace App\Utils;

class ApiResponse
{
    public static function success($data = null, ?string $message = null): array
    {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($message) {
            $response['message'] = $message;
        }

        return $response;
    }

    public static function error(string $message, int $code = 400, $data = null): array
    {
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $code,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($data) {
            $response['data'] = $data;
        }

        return $response;
    }

    public static function toolResult(string $toolName, $result, array $arguments = []): array
    {
        return [
            'success' => true,
            'tool' => $toolName,
            'result' => $result,
            'arguments' => $arguments,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function toolError(string $toolName, string $error, array $arguments = []): array
    {
        return [
            'success' => false,
            'tool' => $toolName,
            'error' => $error,
            'arguments' => $arguments,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function serverInfo(array $serverData): array
    {
        return [
            'success' => true,
            'server' => $serverData,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function status(array $statusData): array
    {
        return [
            'success' => true,
            'status' => $statusData,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function logs(array $logs): array
    {
        return [
            'success' => true,
            'logs' => $logs,
            'count' => count($logs),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function metrics(array $metrics): array
    {
        return [
            'success' => true,
            'metrics' => $metrics,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    public static function toolsList(array $tools): array
    {
        return [
            'success' => true,
            'tools' => $tools,
            'count' => count($tools),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}