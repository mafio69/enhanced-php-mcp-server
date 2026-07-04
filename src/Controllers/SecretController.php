<?php

namespace App\Controllers;

use App\Config\ServerConfig;
use App\Services\SecretManagerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Throwable;

class SecretController extends BaseController
{
    private SecretManagerService $secretManager;

    public function __construct(
        ServerConfig $config,
        LoggerInterface $logger,
        SecretManagerService $secretManager
    ) {
        parent::__construct($config, $logger);
        $this->secretManager = $secretManager;
    }

    public function listSecrets(Request $request, Response $response): Response
    {
        try {
            $secrets = $this->secretManager->listSecrets();

            return $this->jsonResponse($response, ['success' => true, 'data' => $secrets], 200);
        } catch (Throwable $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function storeSecret(Request $request, Response $response): Response
    {
        try {
            $body = $this->getRequestBody($request);
            $key = $body['key'] ?? '';
            $value = $body['value'] ?? '';

            if (trim($key) === '' || trim((string)$value) === '') {
                return $this->jsonResponse($response, ['success' => false, 'error' => 'Missing fields'], 400);
            }

            $this->secretManager->storeSecret($key, $value);

            return $this->jsonResponse($response, ['success' => true], 200);
        } catch (Throwable $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function getSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key', '');
        if ($key === '') {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Missing key'], 400);
        }

        $value = $this->secretManager->getSecret($key);
        if ($value === null) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Not found'], 404);
        }

        return $this->jsonResponse($response, ['success' => true, 'data' => ['key' => $key, 'value' => $value]], 200);
    }

    public function deleteSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key', '');
        if ($key === '') {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Missing key'], 400);
        }

        if (!$this->secretManager->secretExists($key)) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Not found'], 404);
        }

        $this->secretManager->deleteSecret($key);

        return $this->jsonResponse($response, ['success' => true], 200);
    }

    public function checkSecret(Request $request, Response $response): Response
    {
        $key = $request->getAttribute('key', '');
        $exists = $this->secretManager->secretExists($key);

        return $this->jsonResponse($response, ['success' => true, 'data' => ['exists' => $exists]], 200);
    }

    public function encryptValue(Request $request, Response $response): Response
    {
        $body = $this->getRequestBody($request);
        $value = $body['value'] ?? '';

        if (trim((string)$value) === '') {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Missing field'], 400);
        }

        $encrypted = $this->secretManager->encrypt($value);

        return $this->jsonResponse($response, ['success' => true, 'data' => ['encrypted' => $encrypted]], 200);
    }

    public function decryptValue(Request $request, Response $response): Response
    {
        $body = $this->getRequestBody($request);
        $encrypted = $body['encrypted'] ?? '';

        if (trim((string)$encrypted) === '') {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Missing field'], 400);
        }

        try {
            $decrypted = $this->secretManager->decrypt($encrypted);

            return $this->jsonResponse($response, ['success' => true, 'data' => ['decrypted' => $decrypted]], 200);
        } catch (Throwable $e) {
            return $this->jsonResponse($response, ['success' => false, 'error' => 'Invalid data'], 500);
        }
    }
}
