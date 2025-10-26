<?php

namespace Tests\Unit\Tools;

use App\Tools\HelloTool;
use PHPUnit\Framework\TestCase;

class HelloToolTest extends TestCase
{
    private HelloTool $tool;

    protected function setUp(): void
    {
        $this->tool = new HelloTool();
    }

    public function testGetName(): void
    {
        $this->assertEquals('hello', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals('Zwraca powitanie', $this->tool->getDescription());
    }

    public function testExecuteWithName(): void
    {
        $result = $this->tool->execute(['name' => 'Test']);
        $this->assertEquals('Hello, Test! Nice to meet you.', $result);
    }

    public function testExecuteWithoutName(): void
    {
        $result = $this->tool->execute();
        $this->assertEquals('Hello, World! Nice to meet you.', $result);
    }

    public function testGetSchema(): void
    {
        $schema = $this->tool->getSchema();
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertEmpty($schema['required']);
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->tool->isEnabled());
    }
}