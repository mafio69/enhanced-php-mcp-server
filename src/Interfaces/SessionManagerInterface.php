<?php

namespace App\Interfaces;

/**
 * Interface for session management
 *
 * Handles user authentication sessions.
 */
interface SessionManagerInterface
{
    public function createSession(string $username): string;
    public function validateSession(string $sessionId): ?array;
    public function deleteSession(string $sessionId): void;
    public function getSessionFromRequest(): ?string;
    public function isAuthenticated(): bool;
    public function getCurrentUser(): ?array;
}