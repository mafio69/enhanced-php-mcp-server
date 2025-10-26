<?php

namespace App\Services;

use App\Interfaces\ToolExecutorInterface;
use Exception;
use Psr\Log\LoggerInterface;

class ToolsService
{
    private ToolExecutorInterface $toolExecutor;
    private LoggerInterface $logger;

    public function __construct(ToolExecutorInterface $toolExecutor, LoggerInterface $logger)
    {
        $this->toolExecutor = $toolExecutor;
        $this->logger = $logger;
    }

    public function getAvailableTools(): array
    {
        try {
            $tools = $this->toolExecutor->getTools();
            $this->logger->info('Tools list accessed', ['count' => count($tools)]);
            return $tools;
        } catch (Exception $e) {
            $this->logger->error('Failed to get tools list', ['error' => $e->getMessage()]);
            throw new Exception('Failed to get tools: ' . $e->getMessage());
        }
    }

    public function executeTool(string $toolName, array $arguments = []): array
    {
        try {
            if (empty($toolName)) {
                throw new Exception('Tool name is required');
            }

            $this->logger->info('Executing tool', ['tool' => $toolName, 'args' => $arguments]);

            $startTime = microtime(true);
            $result = $this->toolExecutor->executeTool($toolName, $arguments);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $this->logger->info('Tool executed successfully', [
                'tool' => $toolName,
                'execution_time' => $executionTime . 'ms'
            ]);

            return [
                'success' => true,
                'result' => $result,
                'tool' => $toolName,
                'execution_time' => $executionTime,
                'arguments' => $arguments
            ];
        } catch (Exception $e) {
            $this->logger->error('Tool execution failed', [
                'tool' => $toolName,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'tool' => $toolName,
                'arguments' => $arguments
            ];
        }
    }

    public function getToolSchema(string $toolName): ?array
    {
        try {
            $tools = $this->getAvailableTools();

            foreach ($tools as $tool) {
                if ($tool['name'] === $toolName) {
                    return $tool;
                }
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error('Failed to get tool schema', [
                'tool' => $toolName,
                'error' => $e->getMessage()
            ]);
            throw new Exception('Failed to get tool schema: ' . $e->getMessage());
        }
    }

    public function getDefaultArguments(string $toolName): array
    {
        try {
            $schema = $this->getToolSchema($toolName);

            if (!$schema || !isset($schema['inputSchema']['properties'])) {
                return [];
            }

            $defaults = [];
            $properties = $schema['inputSchema']['properties'];

            foreach ($properties as $propName => $propData) {
                if (isset($propData['default'])) {
                    $defaults[$propName] = $propData['default'];
                } elseif (isset($propData['type'])) {
                    // Set sensible defaults based on type
                    switch ($propData['type']) {
                        case 'string':
                            $defaults[$propName] = '';
                            break;
                        case 'integer':
                        case 'number':
                            $defaults[$propName] = 0;
                            break;
                        case 'boolean':
                            $defaults[$propName] = false;
                            break;
                        case 'array':
                            $defaults[$propName] = [];
                            break;
                        case 'object':
                            $defaults[$propName] = (object) [];
                            break;
                    }
                }
            }

            return $defaults;
        } catch (Exception $e) {
            $this->logger->error('Failed to get default arguments', [
                'tool' => $toolName,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function validateToolArguments(string $toolName, array $arguments): array
    {
        try {
            $schema = $this->getToolSchema($toolName);

            if (!$schema || !isset($schema['inputSchema'])) {
                return ['valid' => true, 'errors' => []];
            }

            $inputSchema = $schema['inputSchema'];
            $errors = [];

            // Check required properties
            if (isset($inputSchema['required'])) {
                foreach ($inputSchema['required'] as $requiredProp) {
                    if (!array_key_exists($requiredProp, $arguments)) {
                        $errors[] = "Missing required property: {$requiredProp}";
                    }
                }
            }

            // Check property types
            if (isset($inputSchema['properties'])) {
                foreach ($arguments as $propName => $propValue) {
                    if (isset($inputSchema['properties'][$propName])) {
                        $propSchema = $inputSchema['properties'][$propName];
                        $expectedType = $propSchema['type'] ?? null;

                        if ($expectedType && !$this->validateType($propValue, $expectedType)) {
                            $errors[] = "Property '{$propName}' should be of type {$expectedType}";
                        }
                    }
                }
            }

            return [
                'valid' => empty($errors),
                'errors' => $errors
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to validate tool arguments', [
                'tool' => $toolName,
                'arguments' => $arguments,
                'error' => $e->getMessage()
            ]);
            return ['valid' => false, 'errors' => [$e->getMessage()]];
        }
    }

    private function validateType($value, string $expectedType): bool
    {
        switch ($expectedType) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value);
            case 'number':
                return is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'object':
                return is_object($value) || (is_array($value) && array_keys($value) !== range(0, count($value) - 1));
            default:
                return true;
        }
    }

    public function getToolsByCategory(): array
    {
        try {
            $tools = $this->getAvailableTools();
            $categorized = [];

            foreach ($tools as $tool) {
                $category = $this->categorizeTool($tool['name']);
                $categorized[$category][] = $tool;
            }

            // Sort categories and tools
            ksort($categorized);
            foreach ($categorized as &$category) {
                usort($category, function($a, $b) {
                    return strcmp($a['name'], $b['name']);
                });
            }

            return $categorized;
        } catch (Exception $e) {
            $this->logger->error('Failed to categorize tools', ['error' => $e->getMessage()]);
            throw new Exception('Failed to categorize tools: ' . $e->getMessage());
        }
    }

    private function categorizeTool(string $toolName): string
    {
        $categories = [
            'System' => ['system_info', 'get_time'],
            'Files' => ['read_file', 'write_file', 'list_files'],
            'Network' => ['http_request', 'brave_search', 'get_weather'],
            'Data' => ['json_parse'],
            'Utility' => ['hello', 'calculate']
        ];

        foreach ($categories as $category => $tools) {
            if (in_array($toolName, $tools)) {
                return $category;
            }
        }

        return 'Other';
    }
}