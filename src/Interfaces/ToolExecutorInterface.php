<?php

namespace App\Interfaces;

/**
 * Interface for tool execution management
 *
 * Handles tool registration, discovery, and execution.
 */
interface ToolExecutorInterface
{
    public function executeTool(string $toolName, array $arguments = []): string;
    public function registerTool(ToolInterface $tool): void;
    public function hasTool(string $toolName): bool;
    public function getTools(): array;
}