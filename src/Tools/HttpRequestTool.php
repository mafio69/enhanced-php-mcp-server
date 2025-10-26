<?php

namespace App\Tools;

use App\Interfaces\ToolInterface;

class HttpRequestTool implements ToolInterface
{
    public function execute(array $arguments = []): string
    {
        $url = $arguments['url'] ?? '';
        $method = strtoupper($arguments['method'] ?? 'GET');
        $headers = $arguments['headers'] ?? '{}';
        $body = $arguments['body'] ?? '';

        if (empty($url)) throw new \Exception("URL jest wymagany");
        if (!filter_var($url, FILTER_VALIDATE_URL)) throw new \Exception("Nieprawidłowy URL: {$url}");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (!empty($headers)) {
            $headersArray = json_decode($headers, true) ?? [];
            $headerLines = [];
            foreach ($headersArray as $key => $value) {
                $headerLines[] = "{$key}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerLines);
        }

        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return "=== ODPOWIEDŹ HTTP ===\nURL: {$url}\nMetoda: {$method}\nStatus: {$httpCode}\n\n{$response}";
    }

    public function getName(): string { return 'http_request'; }
    public function getDescription(): string { return 'Wykonuje zapytanie HTTP do zewnętrznego API'; }
    public function getSchema(): array {
        return [
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string', 'description' => 'URL do wywołania'],
                'method' => ['type' => 'string', 'description' => 'Metoda HTTP'],
                'headers' => ['type' => 'string', 'description' => 'Nagłówki JSON'],
                'body' => ['type' => 'string', 'description' => 'Treść zapytania']
            ],
            'required' => ['url']
        ];
    }
    public function isEnabled(): bool { return true; }
}