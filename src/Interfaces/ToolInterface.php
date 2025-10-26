<?php

namespace App\Interfaces;

/**
 * Interface for MCP tools
 *
 * Defines the contract that all tool implementations must follow.
 * Each tool should be able to execute itself and provide metadata.
 */
interface ToolInterface
{
    /**
     * Execute the tool with given arguments
     *
     * @param array $arguments Tool arguments
     * @return string Tool execution result
     * @throws \Exception When tool execution fails
     */
    public function execute(array $arguments = []): string;

    /**
     * Get the tool name
     *
     * @return string Tool identifier
     */
    public function getName(): string;

    /**
     * Get the tool description
     *
     * @return string Human-readable description
     */
    public function getDescription(): string;

    /**
     * Get the tool input schema
     *
     * @return array JSON schema for tool input validation
     */
    public function getSchema(): array;

    /**
     * Check if the tool is enabled
     *
     * @return bool True if tool can be used
     */
    public function isEnabled(): bool;
}