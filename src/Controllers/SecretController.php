<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\Services\SecretService;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class SecretController extends BaseController
{
    private SecretService $secretService;

    public function __construct(ServerConfig $config, LoggerInterface $logger, SecretService $secretService)
    {
        parent::__construct($config, $logger);
        $this->secretService = $secretService;
    }

    /**
     * List all stored secrets (keys only)
     */
    public function listSecrets(Request $request, Response $response): Response
    {
        try {
            $secrets = $this->secretService->listSecrets();
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $secrets,
                'count' => count($secrets),
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to list secrets'));
        }
    }

    /**
     * Get a specific secret value
     */
    public function getSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key');

        if (empty($key)) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Secret key is required'));
        }

        try {
            $secret = $this->secretService->getSecret($key);

            if ($secret === null) {
                return $this->errorResponse($response, \App\DTO\ErrorResponse::notFound('Secret not found'));
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'key' => $key,
                    'value' => $secret->getValue(),
                    'description' => $secret->getDescription(),
                    'exists' => true,
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to retrieve secret'));
        }
    }

    /**
     * Store or update a secret
     */
    public function storeSecret(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['key']) || !isset($data['value'])) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Key and value are required'));
        }

        $key = trim($data['key']);
        $value = trim($data['value']);

        $description = $data['description'] ?? '';

        if (empty($key) || empty($value)) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Key and value cannot be empty'));
        }

        try {
            $this->secretService->storeSecret($key, $value, $description);
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Secret stored successfully',
                'data' => [
                    'key' => $key,
                    'stored' => true,
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to store secret'));
        }
    }

    /**
     * Delete a secret
     */
    public function deleteSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key');

        if (empty($key)) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Secret key is required'));
        }

        try {
            if (!$this->secretService->checkSecret($key)) {
                return $this->errorResponse($response, \App\DTO\ErrorResponse::notFound('Secret not found'));
            }

            $deleted = $this->secretService->deleteSecret($key);

            if ($deleted) {
                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Secret deleted successfully',
                    'data' => [
                        'key' => $key,
                        'deleted' => true,
                    ],
                ]);
            } else {
                return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to delete secret'));
            }
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to delete secret'));
        }
    }

    /**
     * Check if a secret exists
     */
    public function checkSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key');

        if (empty($key)) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Secret key is required'));
        }

        try {
            $exists = $this->secretService->checkSecret($key);
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'key' => $key,
                    'exists' => $exists,
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to check secret'));
        }
    }

    /**
     * Encrypt a value without storing it
     */
    public function encryptValue(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['value'])) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Value is required'));
        }

        $value = trim($data['value']);

        if (empty($value)) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Value cannot be empty'));
        }

        try {
            $encrypted = $this->secretService->encryptValue($value);
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'original' => $value,
                    'encrypted' => $encrypted,
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to encrypt value'));
        }
    }

    /**
     * Decrypt a value
     */
    public function decryptValue(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['encrypted'])) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Encrypted value is required'));
        }

        $encrypted = trim($data['encrypted']);

        if (empty($encrypted)) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::badRequest('Encrypted value cannot be empty'));
        }

        try {
            $decrypted = $this->secretService->decryptValue($encrypted);
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'encrypted' => $encrypted,
                    'decrypted' => $decrypted,
                ],
            ]);
        } catch (Exception $e) {
            return $this->errorResponse(
                $response,
                \App\DTO\ErrorResponse::internalError('Failed to decrypt value. The value may not be properly encrypted.')
            );
        }
    }

    /**
     * Migrate existing configuration secrets to encrypted storage
     */
    public function migrateSecrets(Request $request, Response $response): Response
    {
        try {
            // Get current server configuration
            $configPath = __DIR__.'/../../config/server.php';
            $serverConfig = require $configPath;

            // Extract secrets from config
            $configSecrets = [];
            if (isset($serverConfig['mcpServers'])) {
                foreach ($serverConfig['mcpServers'] as $serverName => $serverData) {
                    if (isset($serverData['mcpServers'])) {
                        foreach ($serverData['mcpServers'] as $serviceName => $serviceData) {
                            if (isset($serviceData['env'])) {
                                foreach ($serviceData['env'] as $envKey => $envValue) {
                                    if (is_string($envValue) && $this->isSecretValue($envValue)) {
                                        $secretKey = "{$serverName}.{$serviceName}.{$envKey}";
                                        $configSecrets[$secretKey] = $envValue;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $result = $this->secretService->migrateSecrets($configSecrets);
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Secret migration completed',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return $this->errorResponse($response, \App\DTO\ErrorResponse::internalError('Failed to migrate secrets'));
        }
    }

    /**
     * Check if a value looks like a secret
     */
    private function isSecretValue(string $value): bool
    {
        // Skip placeholder values
        if ($value === 'YOUR_API_KEY_HERE' || $value === 'YOUR_SECRET_HERE') {
            return false;
        }

        // Check for common secret patterns
        $secretPatterns = [
            '/^sk-/', // OpenAI API keys
            '/^AIza[0-9A-Za-z_-]{35}$/', // Google API keys
            '/^[A-Za-z0-9]{32,}$/', // Generic long keys
            '/^BRAVE_API_KEY/',
            '/^[A-Za-z0-9_-]{20,}$/', // Generic API keys
        ];

        foreach ($secretPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }
}