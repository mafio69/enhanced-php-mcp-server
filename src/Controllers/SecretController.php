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
            return $this->errorResponse($response, 'Failed to list secrets', 500);
        }
    }

    /**
     * Get a specific secret value
     */
    public function getSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key');

        if (empty($key)) {
            return $this->errorResponse($response, 'Secret key is required', 400);
        }

        try {
            $secret = $this->secretService->getSecret($key);

            if ($secret === null) {
                return $this->errorResponse($response, 'Secret not found', 404);
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
            return $this->errorResponse($response, 'Failed to retrieve secret', 500);
        }
    }

    /**
     * Store or update a secret
     */
    public function storeSecret(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['key']) || !isset($data['value'])) {
            return $this->errorResponse($response, 'Key and value are required', 400);
        }

        $key = trim($data['key']);
        $value = trim($data['value']);

        $description = $data['description'] ?? '';

        if (empty($key) || empty($value)) {
            return $this->errorResponse($response, 'Key and value cannot be empty', 400);
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
            return $this->errorResponse($response, 'Failed to store secret', 500);
        }
    }

    /**
     * Delete a secret
     */
    public function deleteSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key');

        if (empty($key)) {
            return $this->errorResponse($response, 'Secret key is required', 400);
        }

        try {
            if (!$this->secretService->checkSecret($key)) {
                return $this->errorResponse($response, 'Secret not found', 404);
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
                return $this->errorResponse($response, 'Failed to delete secret', 500);
            }
        } catch (Exception $e) {
            return $this->errorResponse($response, 'Failed to delete secret', 500);
        }
    }

    /**
     * Check if a secret exists
     */
    public function checkSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key');

        if (empty($key)) {
            return $this->errorResponse($response, 'Secret key is required', 400);
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
            return $this->errorResponse($response, 'Failed to check secret', 500);
        }
    }

    /**
     * Encrypt a value without storing it
     */
    public function encryptValue(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['value'])) {
            return $this->errorResponse($response, 'Value is required', 400);
        }

        $value = trim($data['value']);

        if (empty($value)) {
            return $this->errorResponse($response, 'Value cannot be empty', 400);
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
            return $this->errorResponse($response, 'Failed to encrypt value', 500);
        }
    }

    /**
     * Decrypt a value
     */
    public function decryptValue(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!isset($data['encrypted'])) {
            return $this->errorResponse($response, 'Encrypted value is required', 400);
        }

        $encrypted = trim($data['encrypted']);

        if (empty($encrypted)) {
            return $this->errorResponse($response, 'Encrypted value cannot be empty', 400);
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
                'Failed to decrypt value. The value may not be properly encrypted.',
                500
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
            return $this->errorResponse($response, 'Failed to migrate secrets', 500);
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