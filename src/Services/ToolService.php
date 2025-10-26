<?php

namespace App\Services;

use App\Config\ServerConfig;
use DateTime;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;

class ToolService
{
    private ServerConfig $config;
    private LoggerInterface $logger;

    public function __construct(ServerConfig $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function executeTool(string $toolName, array $arguments = []): string
    {
        if (!$this->config->isToolEnabled($toolName)) {
            throw new Exception("Tool '$toolName' is not enabled");
        }

        $this->logger->info("Executing tool: $toolName", ['arguments' => $arguments]);

        try {
            // First check if tool is known
            $knownTools = [
                'hello',
                'get_time',
                'calculate',
                'list_files',
                'read_file',
                'write_file',
                'system_info',
                'http_request',
                'json_parse',
                'get_weather',
            ];

            if (!in_array($toolName, $knownTools)) {
                throw new Exception("Unknown tool: $toolName");
            }

            $result = match ($toolName) {
                'hello' => $this->executeHello($arguments),
                'get_time' => $this->executeGetTime($arguments),
                'calculate' => $this->executeCalculate($arguments),
                'list_files' => $this->executeListFiles($arguments),
                'read_file' => $this->executeReadFile($arguments),
                'write_file' => $this->executeWriteFile($arguments),
                'system_info' => $this->executeSystemInfo($arguments),
                'http_request' => $this->executeHttpRequest($arguments),
                'json_parse' => $this->executeJsonParse($arguments),
                'get_weather' => $this->executeGetWeather($arguments),
            };

            $this->logger->info("Tool executed successfully", ['tool' => $toolName]);

            return $result;

        } catch (Exception $e) {
            $this->logger->error("Tool execution failed", ['tool' => $toolName, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function executeHello(array $args): string
    {
        $name = $args['name'] ?? 'Unknown';

        return "Hello, $name! Nice to meet you.";
    }

    private function executeGetTime(array $args): string
    {
        $format = $args['format'] ?? 'Y-m-d H:i:s';
        $timezone = $args['timezone'] ?? 'UTC';

        try {
            $date = new DateTime('now', new DateTimeZone($timezone));

            return "Current time: ".$date->format($format)." ($timezone)";
        } catch (Exception $e) {
            return "Current time: ".date($format);
        }
    }

    private function executeCalculate(array $args): string
    {
        $operation = $args['operation'] ?? 'add';
        $a = $args['a'] ?? 0;
        $b = $args['b'] ?? 0;

        if (!is_numeric($a) || !is_numeric($b)) {
            return "Error: Both arguments must be numbers";
        }

        $a = (float)$a;
        $b = (float)$b;

        return match ($operation) {
            'add' => "Result: ".($a + $b),
            'subtract' => "Result: ".($a - $b),
            'multiply' => "Result: ".($a * $b),
            'divide' => $b != 0 ? "Result: ".($a / $b) : "Error: Division by zero",
            default => "Unknown operation: $operation"
        };
    }

    private function executeListFiles(array $args): string
    {
        $path = $args['path'] ?? '.';
        $fullPath = $this->validatePath($path);

        if (!is_dir($fullPath)) {
            throw new Exception("Directory not found: $path");
        }

        $result = "Files in directory: $path\n";
        $result .= str_repeat("=", 50)."\n";

        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath.'/'.$item;
            $type = is_dir($itemPath) ? '[DIR]' : '[FILE]';
            $size = is_file($itemPath) ? ' ('.$this->formatBytes(filesize($itemPath)).')' : '';
            $modified = date('Y-m-d H:i:s', filemtime($itemPath));

            $result .= sprintf("%-10s %-30s %s %s\n", $type, $item, $modified, $size);
        }

        return $result;
    }

    private function executeReadFile(array $args): string
    {
        $path = $args['path'] ?? '';
        if (empty($path)) {
            throw new Exception("File path is required");
        }

        $fullPath = $this->validatePath($path);

        if (!file_exists($fullPath)) {
            throw new Exception("File not found: $path");
        }

        if (!is_readable($fullPath)) {
            throw new Exception("Cannot read file: $path");
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw new Exception("Failed to read file: $path");
        }

        $result = "File: $path\n";
        $result .= str_repeat("=", 50)."\n";
        $result .= "Size: ".$this->formatBytes(strlen($content))."\n";
        $result .= "Modified: ".date('Y-m-d H:i:s', filemtime($fullPath))."\n";
        $result .= str_repeat("-", 50)."\n";
        $result .= $content;

        return $result;
    }

    private function executeWriteFile(array $args): string
    {
        $path = $args['path'] ?? '';
        $content = $args['content'] ?? '';

        if (empty($path)) {
            throw new Exception("File path is required");
        }

        $fullPath = $this->validatePath($path);

        // Check file size limit
        if (strlen($content) > $this->config->getMaxFileSize()) {
            throw new Exception(
                "Content too large. Maximum size: ".$this->formatBytes($this->config->getMaxFileSize())
            );
        }

        $bytesWritten = file_put_contents($fullPath, $content);
        if ($bytesWritten === false) {
            throw new Exception("Failed to write file: $path");
        }

        return "File saved: $path\nBytes written: $bytesWritten";
    }

    private function executeSystemInfo(array $args): string
    {
        $info = "=== SYSTEM INFORMATION ===\n";
        $info .= "Operating System: ".php_uname()."\n";
        $info .= "PHP Version: ".PHP_VERSION."\n";
        $info .= "Architecture: ".(PHP_INT_SIZE * 8)."-bit\n";
        $info .= "Hostname: ".gethostname()."\n";
        $info .= "Memory Usage: ".$this->formatBytes(memory_get_usage(true))."\n";
        $info .= "Peak Memory: ".$this->formatBytes(memory_get_peak_usage(true))."\n";
        $info .= "Server Software: ".($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown')."\n";
        $info .= "Document Root: ".($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown')."\n";

        return $info;
    }

    private function executeHttpRequest(array $args): string
    {
        $url = $args['url'] ?? '';
        $method = $args['method'] ?? 'GET';
        $headers = $args['headers'] ?? [];
        $data = $args['data'] ?? null;

        if (empty($url)) {
            throw new Exception("URL is required");
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL: $url");
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config->getHttpTimeout());
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config->getHttpUserAgent());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->config->areHttpRedirectsAllowed());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($data !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new Exception("HTTP request failed: $error");
        }

        $result = "=== HTTP RESPONSE ===\n";
        $result .= "URL: $url\n";
        $result .= "Method: $method\n";
        $result .= "Status: $httpCode\n";
        $result .= "Response Size: ".$this->formatBytes(strlen($response))."\n";
        $result .= str_repeat("-", 50)."\n";
        $result .= $response;

        return $result;
    }

    private function executeJsonParse(array $args): string
    {
        $json = $args['json'] ?? '';
        if (empty($json)) {
            throw new Exception("JSON string is required");
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON parsing error: ".json_last_error_msg());
        }

        $result = "=== PARSED JSON ===\n";
        $result .= "Root type: ".gettype($decoded)."\n";

        if (is_array($decoded)) {
            $result .= "Element count: ".count($decoded)."\n";
            $result .= str_repeat("-", 50)."\n";
            $result .= json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $result .= str_repeat("-", 50)."\n";
            $result .= json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return $result;
    }

    private function executeGetWeather(array $args): string
    {
        $city = $args['city'] ?? '';
        if (empty($city)) {
            throw new Exception("City name is required");
        }

        // Mock weather data for demo purposes
        $weatherConditions = ['Sunny', 'Cloudy', 'Rainy', 'Partly Cloudy', 'Clear'];
        $condition = $weatherConditions[array_rand($weatherConditions)];
        $temperature = rand(-10, 35);
        $humidity = rand(30, 90);
        $windSpeed = rand(0, 25);

        $result = "=== WEATHER FOR: ".strtoupper($city)." ===\n";
        $result .= "Weather condition: $condition\n";
        $result .= "Temperature: $temperature°C\n";
        $result .= "Humidity: $humidity%\n";
        $result .= "Wind speed: $windSpeed km/h\n";
        $result .= "Last updated: ".date('Y-m-d H:i:s')."\n";
        $result .= "\nNote: This is simulated weather data for demonstration purposes.";

        return $result;
    }

    private function validatePath(string $path): string
    {
        // Remove directory traversal attempts
        $path = str_replace(['../', '..\\'], '', $path);

        // Get absolute path
        $fullPath = realpath(__DIR__.'/../../'.$path);

        if ($fullPath === false) {
            // Try relative to current directory
            $fullPath = realpath($path);
        }

        if ($fullPath === false) {
            throw new Exception("Invalid path: $path");
        }

        // Check if path is within allowed directories
        $allowed = false;
        foreach ($this->config->getAllowedPaths() as $allowedPath) {
            $allowedReal = realpath($allowedPath);
            if ($allowedReal && strpos($fullPath, $allowedReal) === 0) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new Exception("Access denied! Path is outside allowed directory.");
        }

        return $fullPath;
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}