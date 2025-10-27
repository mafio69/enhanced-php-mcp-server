<?php

namespace Tests\Unit\Services;

use App\Services\ToolRegistry;
use PHPUnit\Framework\TestCase;

class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ToolRegistry();
    }

    public function testGetTools(): void
    {
        $tools = $this->registry->getTools();
        $this->assertIsArray($tools);
        $this->assertCount(14, $tools); // Updated count including PlaywrightTool, GitHubTool and PHPDiagnosticsTool
    }

    public function testHasTool(): void
    {
        $this->assertTrue($this->registry->hasTool('hello'));
        $this->assertTrue($this->registry->hasTool('calculate'));
        $this->assertTrue($this->registry->hasTool('brave_search'));
        $this->assertFalse($this->registry->hasTool('nonexistent'));
    }

    public function testExecuteTool(): void
    {
        $result = $this->registry->executeTool('hello', ['name' => 'Test']);
        $this->assertEquals('Hello, Test! Nice to meet you.', $result);
    }

    public function testExecuteToolThrowsExceptionForUnknownTool(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nieznane narzędzie: nonexistent');

        $this->registry->executeTool('nonexistent');
    }

    public function testExecuteToolReturnsCorrectFormat(): void
    {
        $result = $this->registry->executeTool('calculate', [
            'operation' => 'add',
            'a' => 2,
            'b' => 3
        ]);

        $this->assertStringContainsString('Wynik: 5', $result);
    }

    public function testGetToolsContainsRequiredFields(): void
    {
        $tools = $this->registry->getTools();
        $firstTool = $tools[0];

        $this->assertArrayHasKey('name', $firstTool);
        $this->assertArrayHasKey('description', $firstTool);
        $this->assertArrayHasKey('inputSchema', $firstTool);
    }
}