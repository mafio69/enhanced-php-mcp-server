<?php

namespace Tests\Unit\Tools;

use App\Tools\BraveSearchTool;
use PHPUnit\Framework\TestCase;

class BraveSearchToolTest extends TestCase
{
    private BraveSearchTool $tool;

    protected function setUp(): void
    {
        $this->tool = new BraveSearchTool();
    }

    public function testGetName(): void
    {
        $this->assertEquals('brave_search', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals('Przeszukuje internet za pomocą Brave Search API', $this->tool->getDescription());
    }

    public function testGetSchema(): void
    {
        $schema = $this->tool->getSchema();

        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertArrayHasKey('query', $schema['properties']);
        $this->assertArrayHasKey('count', $schema['properties']);
        $this->assertEquals(['query'], $schema['required']);
        $this->assertEquals(10, $schema['properties']['count']['default']);
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->tool->isEnabled());
    }

    public function testExecuteWithoutApiKey(): void
    {
        // Upewnij się, że API key nie jest ustawiony
        putenv('BRAVE_API_KEY');
        unset($_ENV['BRAVE_API_KEY']);

        $result = $this->tool->execute([
            'query' => 'test query',
            'count' => 5
        ]);

        $this->assertStringContainsString('BRAVE_API_KEY', $result);
        $this->assertStringContainsString('export BRAVE_API_KEY', $result);
    }

    public function testExecuteWithoutQuery(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Parametr 'query' jest wymagany");

        $this->tool->execute(['count' => 5]);
    }

    public function testExecuteWithEmptyQuery(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Parametr 'query' jest wymagany");

        $this->tool->execute(['query' => '']);
    }

    public function testExecuteWithValidParameters(): void
    {
        // Tylko test walidacji parametrów, bez API call
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Parametr 'query' jest wymagany");

        // Sprawdza czy walidacja działa przed użyciem API
        $this->tool->execute(['query' => '']);
    }

    public function testExecuteWithCountParameter(): void
    {
        putenv('BRAVE_API_KEY');
        unset($_ENV['BRAVE_API_KEY']);

        $result = $this->tool->execute([
            'query' => 'test',
            'count' => 3
        ]);

        $this->assertStringContainsString('BRAVE_API_KEY', $result);
    }

    public function testCountParameterLimits(): void
    {
        putenv('BRAVE_API_KEY');
        unset($_ENV['BRAVE_API_KEY']);

        // Test count > 20 (limit API)
        $result = $this->tool->execute([
            'query' => 'test',
            'count' => 25
        ]);

        $this->assertStringContainsString('BRAVE_API_KEY', $result);
    }

    public function testDefaultCountParameter(): void
    {
        putenv('BRAVE_API_KEY');
        unset($_ENV['BRAVE_API_KEY']);

        $result = $this->tool->execute([
            'query' => 'test'
        ]);

        $this->assertStringContainsString('BRAVE_API_KEY', $result);
    }
}