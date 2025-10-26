<?php

namespace Tests\Unit\Interfaces;

use App\Interfaces\ToolInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test interface contracts with mock implementation
 */
class ToolInterfaceTest extends TestCase
{
    private ToolInterface $tool;

    protected function setUp(): void
    {
        $this->tool = new class implements ToolInterface {
            public function execute(array $arguments = []): string
            {
                return "Hello, " . ($arguments['name'] ?? 'World') . "!";
            }

            public function getName(): string
            {
                return 'test_tool';
            }

            public function getDescription(): string
            {
                return 'Test tool description';
            }

            public function getSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string']
                    ],
                    'required' => []
                ];
            }

            public function isEnabled(): bool
            {
                return true;
            }
        };
    }

    public function testToolInterfaceMethodsExist(): void
    {
        $this->assertTrue(method_exists($this->tool, 'execute'));
        $this->assertTrue(method_exists($this->tool, 'getName'));
        $this->assertTrue(method_exists($this->tool, 'getDescription'));
        $this->assertTrue(method_exists($this->tool, 'getSchema'));
        $this->assertTrue(method_exists($this->tool, 'isEnabled'));
    }

    public function testToolExecution(): void
    {
        $result = $this->tool->execute(['name' => 'Test']);
        $this->assertEquals('Hello, Test!', $result);
    }

    public function testToolMetadata(): void
    {
        $this->assertEquals('test_tool', $this->tool->getName());
        $this->assertEquals('Test tool description', $this->tool->getDescription());
        $this->assertTrue($this->tool->isEnabled());
        $this->assertIsArray($this->tool->getSchema());
    }
}