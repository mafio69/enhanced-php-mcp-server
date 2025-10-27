<?php

namespace App\Controllers;

use App\Context\UserContext;
use App\DTO\ErrorResponse;
use App\DTO\SuccessResponse;
use App\Services\AuthenticationService;
use App\Services\SecretAutoLoader;
use App\Services\UserSecretService;
use App\Services\UserAwareLogger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends BaseController
{
    private AuthenticationService $authService;
    private UserSecretService $userSecretService;
    private SecretAutoLoader $secretAutoLoader;
    private UserAwareLogger $logger;

    public function __construct(
        AuthenticationService $authService,
        UserSecretService $userSecretService,
        SecretAutoLoader $secretAutoLoader,
        UserAwareLogger $logger
    ) {
        $this->authService = $authService;
        $this->userSecretService = $userSecretService;
        $this->secretAutoLoader = $secretAutoLoader;
        $this->logger = $logger;

        // Set up auto-loader integration
        $this->authService->setSecretAutoLoader($this->secretAutoLoader);
    }

    /**
     * User login with automatic secret loading
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $rememberMe = !empty($data['remember_me']);

        // Validate input
        if (empty($email) || empty($password)) {
            return $this->errorResponse($response, ErrorResponse::badRequest('Email and password are required'));
        }

        try {
            $sessionId = $this->authService->login($email, $password, $rememberMe);

            if (!$sessionId) {
                return $this->errorResponse($response, ErrorResponse::unauthorized('Invalid email or password'));
            }

            // Get user context and auto-loaded secrets info
            $userContext = $this->authService->createUserContext($sessionId);
            $loadedSecrets = $this->secretAutoLoader->getLoadedSecrets();
            $secretStats = $this->secretAutoLoader->getLoadedSecretsStats();

            return $this->jsonResponse($response, SuccessResponse::created([
                'session_id' => $sessionId,
                'user' => $userContext->getUser()?->toArray(),
                'secrets' => [
                    'loaded_count' => count($loadedSecrets),
                    'total_count' => $secretStats['total_count'],
                    'owned_count' => $secretStats['owned_count'],
                    'shared_count' => $secretStats['shared_count'],
                    'categories' => $secretStats['categories'],
                    'auto_loaded' => true
                ]
            ], 'Login successful'));

        } catch (\Exception $e) {
            $this->logger->error('Login API error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Login failed. Please try again.'));
        }
    }

    /**
     * User logout with secret cleanup
     */
    public function logout(Request $request, Response $response): Response
    {
        $sessionId = $this->getSessionIdFromRequest($request);

        if (!$sessionId) {
            return $this->errorResponse($response, ErrorResponse::unauthorized('No active session'));
        }

        try {
            $success = $this->authService->logout($sessionId);

            if (!$success) {
                return $this->errorResponse($response, ErrorResponse::notFound('Session not found'));
            }

            return $this->jsonResponse($response, SuccessResponse::ok([], 'Logout successful'));

        } catch (\Exception $e) {
            $this->logger->error('Logout API error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Logout failed. Please try again.'));
        }
    }

    /**
     * Get current user session info
     */
    public function me(Request $request, Response $response): Response
    {
        $sessionId = $this->getSessionIdFromRequest($request);

        if (!$sessionId) {
            return $this->errorResponse($response, ErrorResponse::unauthorized('No active session'));
        }

        try {
            $userContext = $this->authService->createUserContext($sessionId);

            if (!$userContext->isAuthenticated()) {
                return $this->errorResponse($response, ErrorResponse::unauthorized('Session expired'));
            }

            $user = $userContext->getUser();
            $loadedSecrets = $this->secretAutoLoader->getLoadedSecrets();
            $secretStats = $this->secretAutoLoader->getLoadedSecretsStats();

            return $this->jsonResponse($response, SuccessResponse::ok([
                'user' => $user->toArray(),
                'session' => [
                    'session_id' => $sessionId,
                    'ip_address' => $userContext->getIpAddress(),
                    'user_agent' => $userContext->getUserAgent()
                ],
                'secrets' => [
                    'loaded_count' => count($loadedSecrets),
                    'total_count' => $secretStats['total_count'],
                    'owned_count' => $secretStats['owned_count'],
                    'shared_count' => $secretStats['shared_count'],
                    'categories' => $secretStats['categories'],
                    'expiring_soon' => $secretStats['expiring_soon'],
                    'expired' => $secretStats['expired'],
                    'auto_load_enabled' => $this->secretAutoLoader->isAutoLoadEnabled()
                ]
            ]));

        } catch (\Exception $e) {
            $this->logger->error('Me API error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Failed to get user info'));
        }
    }

    /**
     * Reload user secrets
     */
    public function reloadSecrets(Request $request, Response $response): Response
    {
        $sessionId = $this->getSessionIdFromRequest($request);

        if (!$sessionId) {
            return $this->errorResponse($response, ErrorResponse::unauthorized('No active session'));
        }

        try {
            $userContext = $this->authService->createUserContext($sessionId);

            if (!$userContext->isAuthenticated()) {
                return $this->errorResponse($response, ErrorResponse::unauthorized('Session expired'));
            }

            $result = $this->secretAutoLoader->reloadSecrets();
            $secretStats = $this->secretAutoLoader->getLoadedSecretsStats();

            return $this->jsonResponse($response, SuccessResponse::ok([
                'loaded_count' => $result['loaded_count'],
                'error_count' => $result['error_count'],
                'secrets' => $result['secrets'],
                'stats' => $secretStats
            ], 'Secrets reloaded successfully'));

        } catch (\Exception $e) {
            $this->logger->error('Reload secrets API error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Failed to reload secrets'));
        }
    }

    /**
     * Toggle auto-load functionality
     */
    public function toggleAutoLoad(Request $request, Response $response): Response
    {
        $sessionId = $this->getSessionIdFromRequest($request);

        if (!$sessionId) {
            return $this->errorResponse($response, ErrorResponse::unauthorized('No active session'));
        }

        $data = $request->getParsedBody() ?? [];
        $enabled = $data['enabled'] ?? null;

        if ($enabled === null) {
            return $this->errorResponse($response, ErrorResponse::badRequest('Enabled parameter is required'));
        }

        try {
            $userContext = $this->authService->createUserContext($sessionId);

            if (!$userContext->isAuthenticated()) {
                return $this->errorResponse($response, ErrorResponse::unauthorized('Session expired'));
            }

            $this->secretAutoLoader->setAutoLoadEnabled((bool)$enabled);

            if (!$enabled) {
                // Clear loaded secrets if disabling
                $this->secretAutoLoader->clearLoadedSecrets();
            } else {
                // Load secrets if enabling
                $this->secretAutoLoader->loadUserSecrets();
            }

            $secretStats = $this->secretAutoLoader->getLoadedSecretsStats();

            return $this->jsonResponse($response, SuccessResponse::ok([
                'auto_load_enabled' => (bool)$enabled,
                'loaded_count' => $secretStats['total_count'],
                'message' => $enabled ? 'Auto-load enabled' : 'Auto-load disabled'
            ]));

        } catch (\Exception $e) {
            $this->logger->error('Toggle auto-load API error', [
                'session_id' => $sessionId,
                'enabled' => $enabled,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Failed to toggle auto-load'));
        }
    }

    /**
     * Get loaded secrets details
     */
    public function getLoadedSecrets(Request $request, Response $response): Response
    {
        $sessionId = $this->getSessionIdFromRequest($request);

        if (!$sessionId) {
            return $this->errorResponse($response, ErrorResponse::unauthorized('No active session'));
        }

        try {
            $userContext = $this->authService->createUserContext($sessionId);

            if (!$userContext->isAuthenticated()) {
                return $this->errorResponse($response, ErrorResponse::unauthorized('Session expired'));
            }

            $loadedSecrets = $this->secretAutoLoader->getLoadedSecrets();
            $secretStats = $this->secretAutoLoader->getLoadedSecretsStats();

            return $this->jsonResponse($response, SuccessResponse::ok([
                'secrets' => $loadedSecrets,
                'stats' => $secretStats,
                'auto_load_enabled' => $this->secretAutoLoader->isAutoLoadEnabled()
            ]));

        } catch (\Exception $e) {
            $this->logger->error('Get loaded secrets API error', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Failed to get loaded secrets'));
        }
    }

    /**
     * User registration
     */
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? '';
        $role = $data['role'] ?? 'user';

        try {
            $result = $this->authService->register($email, $password, $name, $role);

            if (!$result['success']) {
                return $this->errorResponse($response, ErrorResponse::badRequest('Registration failed', [
                    'errors' => $result['errors']
                ]));
            }

            return $this->jsonResponse($response, SuccessResponse::created([
                'user' => $result['user']
            ], 'Registration successful'));

        } catch (\Exception $e) {
            $this->logger->error('Registration API error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Registration failed. Please try again.'));
        }
    }

    /**
     * Request password reset
     */
    public function requestPasswordReset(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];
        $email = $data['email'] ?? '';

        if (empty($email)) {
            return $this->errorResponse($response, ErrorResponse::badRequest('Email is required'));
        }

        try {
            $success = $this->authService->requestPasswordReset($email);

            // Always return success for security (don't reveal if email exists)
            $message = $success ? 'Password reset instructions sent' : 'If the email exists, reset instructions will be sent';

            return $this->jsonResponse($response, SuccessResponse::ok([], $message));

        } catch (\Exception $e) {
            $this->logger->error('Password reset request API error', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Password reset request failed. Please try again.'));
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody() ?? [];

        $token = $data['token'] ?? '';
        $newPassword = $data['password'] ?? '';

        if (empty($token) || empty($newPassword)) {
            return $this->errorResponse($response, ErrorResponse::badRequest('Token and password are required'));
        }

        try {
            $result = $this->authService->resetPassword($token, $newPassword);

            if (!$result['success']) {
                return $this->errorResponse($response, ErrorResponse::badRequest('Password reset failed', [
                    'errors' => $result['errors']
                ]));
            }

            return $this->jsonResponse($response, SuccessResponse::ok([], 'Password reset successful'));

        } catch (\Exception $e) {
            $this->logger->error('Password reset API error', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ]);

            return $this->errorResponse($response, ErrorResponse::internalServerError('Password reset failed. Please try again.'));
        }
    }

    // Helper methods

    private function getSessionIdFromRequest(Request $request): ?string
    {
        // Check Authorization header first
        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            return $matches[1];
        }

        // Fall back to session cookie
        $cookies = $request->getCookieParams();
        return $cookies['session_id'] ?? null;
    }
}