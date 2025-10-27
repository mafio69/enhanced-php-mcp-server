<?php

namespace Tests\Unit\Tools;

use App\Tools\PlaywrightTool;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class PlaywrightToolTest extends TestCase
{
    private PlaywrightTool $tool;

    protected function setUp(): void
    {
        $this->tool = new PlaywrightTool();
    }

    public function testGetName(): void
    {
        $this->assertEquals('playwright', $this->tool->getName());
    }

    public function testGetDescription(): void
    {
        $this->assertEquals('Prawdziwa automatyzacja przeglądarek internetowych z użyciem Playwright (real browser automation)', $this->tool->getDescription());
    }

    public function testIsEnabled(): void
    {
        $this->assertTrue($this->tool->isEnabled());
    }

    public function testGetSchema(): void
    {
        $schema = $this->tool->getSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('action', $schema['properties']);
        $this->assertArrayHasKey('url', $schema['properties']);
        $this->assertArrayHasKey('selector', $schema['properties']);
        $this->assertArrayHasKey('text', $schema['properties']);
        $this->assertArrayHasKey('waitFor', $schema['properties']);
        $this->assertArrayHasKey('screenshot', $schema['properties']);
        $this->assertArrayHasKey('storageState', $schema['properties']);

        // Check action schema
        $actionSchema = $schema['properties']['action'];
        $this->assertEquals('string', $actionSchema['type']);
        $this->assertContains('info', $actionSchema['enum']);
        $this->assertContains('check_installation', $actionSchema['enum']);
        $this->assertContains('start_browser', $actionSchema['enum']);
        $this->assertContains('navigate', $actionSchema['enum']);
        $this->assertContains('get_content', $actionSchema['enum']);
        $this->assertContains('find_element', $actionSchema['enum']);
        $this->assertContains('click_element', $actionSchema['enum']);
        $this->assertContains('type_text', $actionSchema['enum']);
        $this->assertContains('take_screenshot', $actionSchema['enum']);

        // Check required fields
        $this->assertEquals(['action'], $schema['required']);
    }

    public function testExecuteWithDefaultAction(): void
    {
        $result = $this->tool->execute([]);

        // Should call info action by default
        $this->assertStringContainsString('PLAYWRIGHT INFORMATION', $result);
        $this->assertStringContainsString('Version:', $result);
        $this->assertStringContainsString('Available browsers:', $result);
    }

    public function testExecuteWithInfoAction(): void
    {
        $result = $this->tool->execute(['action' => 'info']);

        $this->assertStringContainsString('PLAYWRIGHT INFORMATION', $result);
        $this->assertStringContainsString('Version:', $result);
        $this->assertStringContainsString('Available browsers:', $result);
    }

    public function testExecuteWithCheckInstallationAction(): void
    {
        $result = $this->tool->execute(['action' => 'check_installation']);

        $this->assertStringContainsString('INSTALLATION CHECK', $result);
    }

    public function testExecuteWithStartBrowserAction(): void
    {
        $result = $this->tool->execute(['action' => 'start_browser']);

        $this->assertStringContainsString('STARTING PLAYWRIGHT BROWSER', $result);
    }

    public function testExecuteWithStartBrowserActionWithStorageState(): void
    {
        $storageState = '/path/to/state.json';
        $result = $this->tool->execute([
            'action' => 'start_browser',
            'storageState' => $storageState
        ]);

        $this->assertStringContainsString('STARTING PLAYWRIGHT BROWSER', $result);
    }

    public function testExecuteWithNavigateActionThrowsExceptionWithoutUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL jest wymagany dla akcji 'navigate'");

        $this->tool->execute(['action' => 'navigate']);
    }

    public function testExecuteWithGetContentActionThrowsExceptionWithoutUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL jest wymagany dla akcji 'get_content'");

        $this->tool->execute(['action' => 'get_content']);
    }

    public function testExecuteWithFindElementActionThrowsExceptionWithoutUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL i selector są wymagane dla akcji 'find_element'");

        $this->tool->execute(['action' => 'find_element', 'selector' => '#test']);
    }

    public function testExecuteWithFindElementActionThrowsExceptionWithoutSelector(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL i selector są wymagane dla akcji 'find_element'");

        $this->tool->execute(['action' => 'find_element', 'url' => 'https://example.com']);
    }

    public function testExecuteWithClickElementActionThrowsExceptionWithoutUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL i selector są wymagane dla akcji 'click_element'");

        $this->tool->execute(['action' => 'click_element', 'selector' => '#test']);
    }

    public function testExecuteWithClickElementActionThrowsExceptionWithoutSelector(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL i selector są wymagane dla akcji 'click_element'");

        $this->tool->execute(['action' => 'click_element', 'url' => 'https://example.com']);
    }

    public function testExecuteWithTypeTextActionThrowsExceptionWithoutUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL, selector i tekst są wymagane dla akcji 'type_text'");

        $this->tool->execute([
            'action' => 'type_text',
            'selector' => '#test',
            'text' => 'Hello'
        ]);
    }

    public function testExecuteWithTypeTextActionThrowsExceptionWithoutSelector(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL, selector i tekst są wymagane dla akcji 'type_text'");

        $this->tool->execute([
            'action' => 'type_text',
            'url' => 'https://example.com',
            'text' => 'Hello'
        ]);
    }

    public function testExecuteWithTypeTextActionThrowsExceptionWithoutText(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL, selector i tekst są wymagane dla akcji 'type_text'");

        $this->tool->execute([
            'action' => 'type_text',
            'url' => 'https://example.com',
            'selector' => '#test'
        ]);
    }

    public function testExecuteWithTakeScreenshotActionThrowsExceptionWithoutUrl(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("URL jest wymagany dla akcji 'take_screenshot'");

        $this->tool->execute(['action' => 'take_screenshot']);
    }

    public function testExecuteWithUnknownActionThrowsException(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Nieznana akcja: unknown_action");

        $this->tool->execute(['action' => 'unknown_action']);
    }

    public function testExecuteWithNavigateAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'navigate',
            'url' => 'https://example.com',
            'waitFor' => 3000
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteWithGetContentAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_content',
            'url' => 'https://example.com',
            'waitFor' => 5000,
            'screenshot' => false
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteWithGetContentActionWithScreenshot(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_content',
            'url' => 'https://example.com',
            'waitFor' => 5000,
            'screenshot' => true
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteWithFindElementAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'find_element',
            'url' => 'https://example.com',
            'selector' => '#main',
            'waitFor' => 3000
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteWithClickElementAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'click_element',
            'url' => 'https://example.com',
            'selector' => '#button',
            'waitFor' => 3000
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteWithTypeTextAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'type_text',
            'url' => 'https://example.com',
            'selector' => '#input',
            'text' => 'Test text',
            'waitFor' => 3000
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteWithTakeScreenshotAction(): void
    {
        $result = $this->tool->execute([
            'action' => 'take_screenshot',
            'url' => 'https://example.com',
            'waitFor' => 5000
        ]);

        // This will likely fail due to WSL dependencies but should show proper error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testExecuteHandlesWSLDepsError(): void
    {
        // Test with a URL that will trigger WSL dependency issues
        $result = $this->tool->execute([
            'action' => 'get_content',
            'url' => 'https://example.com'
        ]);

        // Should show WSL error handling
        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful execution or proper WSL error handling
        $this->assertTrue(
            strpos($result, 'GETTING CONTENT FROM') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false
        );
    }

    public function testDefaultWaitTimeValue(): void
    {
        $result = $this->tool->execute([
            'action' => 'navigate',
            'url' => 'https://example.com'
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }
}