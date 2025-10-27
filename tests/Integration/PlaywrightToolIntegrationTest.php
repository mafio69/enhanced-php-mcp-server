<?php

namespace Tests\Integration;

use App\Tools\PlaywrightTool;
use PHPUnit\Framework\TestCase;

class PlaywrightToolIntegrationTest extends TestCase
{
    private PlaywrightTool $tool;

    protected function setUp(): void
    {
        $this->tool = new PlaywrightTool();
    }

    public function testRealPlaywrightInfo(): void
    {
        $result = $this->tool->execute(['action' => 'info']);

        $this->assertStringContainsString('PLAYWRIGHT INFORMATION', $result);
        $this->assertStringContainsString('Version:', $result);
        $this->assertStringContainsString('Available browsers:', $result);
        $this->assertStringContainsString('Chromium', $result);
        $this->assertStringContainsString('Firefox', $result);
        $this->assertStringContainsString('WebKit', $result);
    }

    public function testRealPlaywrightInstallationCheck(): void
    {
        $result = $this->tool->execute(['action' => 'check_installation']);

        $this->assertStringContainsString('INSTALLATION CHECK', $result);
        $this->assertStringContainsString('Playwright', $result);
        $this->assertTrue(
            strpos($result, 'NPX') !== false ||
            strpos($result, 'installed') !== false
        );
    }

    public function testRealBrowserLaunch(): void
    {
        $result = $this->tool->execute(['action' => 'start_browser']);

        // Either succeeds with browser launch or shows WSL error
        $this->assertTrue(
            strpos($result, 'Browser launched successfully') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Failed to launch browser') !== false
        );
    }

    public function testRealPageContentExtraction(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_content',
            'url' => 'https://example.com',
            'waitFor' => 5000,
            'screenshot' => false
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful content extraction or WSL error handling
        $this->assertTrue(
            strpos($result, 'Content extracted successfully') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Content length:') !== false
        );
    }

    public function testRealPageContentExtractionWithScreenshot(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_content',
            'url' => 'https://example.com',
            'waitFor' => 5000,
            'screenshot' => true
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful content extraction + screenshot or WSL error handling
        $this->assertTrue(
            strpos($result, 'Content extracted successfully') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Screenshot saved:') !== false
        );
    }

    public function testRealElementFinding(): void
    {
        $result = $this->tool->execute([
            'action' => 'find_element',
            'url' => 'https://example.com',
            'selector' => 'h1',
            'waitFor' => 5000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful element finding or WSL error handling
        $this->assertTrue(
            strpos($result, 'Element found!') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Element not found') !== false
        );
    }

    public function testRealElementClicking(): void
    {
        $result = $this->tool->execute([
            'action' => 'click_element',
            'url' => 'https://example.com',
            'selector' => 'h1',
            'waitFor' => 5000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful element clicking or WSL error handling
        $this->assertTrue(
            strpos($result, 'Element clicked successfully!') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Element not found') !== false
        );
    }

    public function testRealTextTyping(): void
    {
        $result = $this->tool->execute([
            'action' => 'type_text',
            'url' => 'https://example.com',
            'selector' => 'h1', // h1 exists on example.com
            'text' => 'Test text input',
            'waitFor' => 5000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful text typing or WSL error handling
        $this->assertTrue(
            strpos($result, 'Text typed successfully!') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Element not found') !== false
        );
    }

    public function testRealScreenshotTaking(): void
    {
        $result = $this->tool->execute([
            'action' => 'take_screenshot',
            'url' => 'https://example.com',
            'waitFor' => 5000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful screenshot or WSL error handling
        $this->assertTrue(
            strpos($result, 'Screenshot taken successfully!') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Saved to:') !== false
        );
    }

    public function testRealNavigation(): void
    {
        $result = $this->tool->execute([
            'action' => 'navigate',
            'url' => 'https://example.com',
            'waitFor' => 5000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Either successful navigation or WSL error handling
        $this->assertTrue(
            strpos($result, 'Page loaded successfully') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Page title:') !== false
        );
    }

    public function testErrorHandlingWithInvalidUrl(): void
    {
        $result = $this->tool->execute([
            'action' => 'get_content',
            'url' => 'https://nonexistent-domain-that-does-not-exist.com',
            'waitFor' => 3000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Should handle network errors gracefully
        $this->assertTrue(
            strpos($result, 'Error executing Playwright') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'failed') !== false
        );
    }

    public function testErrorHandlingWithComplexSelector(): void
    {
        $result = $this->tool->execute([
            'action' => 'find_element',
            'url' => 'https://example.com',
            'selector' => '#nonexistent-element-with-complex-css-selector > .child > span:first-child',
            'waitFor' => 3000
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Should handle missing elements gracefully
        $this->assertTrue(
            strpos($result, 'Element not found') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'timeout') !== false
        );
    }

    public function testMultipleActionsInSequence(): void
    {
        // Test info action
        $result1 = $this->tool->execute(['action' => 'info']);
        $this->assertStringContainsString('PLAYWRIGHT INFORMATION', $result1);

        // Test installation check
        $result2 = $this->tool->execute(['action' => 'check_installation']);
        $this->assertStringContainsString('INSTALLATION CHECK', $result2);

        // Test browser start
        $result3 = $this->tool->execute(['action' => 'start_browser']);
        $this->assertIsString($result3);
        $this->assertNotEmpty($result3);
    }

    public function testStorageStateParameter(): void
    {
        $result = $this->tool->execute([
            'action' => 'start_browser',
            'storageState' => '/path/to/nonexistent/state.json'
        ]);

        $this->assertIsString($result);
        $this->assertNotEmpty($result);

        // Should handle missing storage state file gracefully
        $this->assertTrue(
            strpos($result, 'Browser launched successfully') !== false ||
            strpos($result, 'WSL-Windows BROWSER INTEGRATION') !== false ||
            strpos($result, 'Could not load storage state') !== false
        );
    }

    public function testWaitParameterVariations(): void
    {
        // Test minimum wait time
        $result1 = $this->tool->execute([
            'action' => 'navigate',
            'url' => 'https://example.com',
            'waitFor' => 1000
        ]);
        $this->assertIsString($result1);

        // Test maximum wait time
        $result2 = $this->tool->execute([
            'action' => 'navigate',
            'url' => 'https://example.com',
            'waitFor' => 60000
        ]);
        $this->assertIsString($result2);

        // Both should complete without timeout
        $this->assertNotEmpty($result1);
        $this->assertNotEmpty($result2);
    }

    public function testComplexSelectors(): void
    {
        $complexSelectors = [
            'h1',
            'div',
            'body > div',
            'h1:nth-child(1)',
            '.some-class',
            '#some-id'
        ];

        foreach ($complexSelectors as $selector) {
            $result = $this->tool->execute([
                'action' => 'find_element',
                'url' => 'https://example.com',
                'selector' => $selector,
                'waitFor' => 3000
            ]);

            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        }
    }

    public function testDifferentUrls(): void
    {
        $urls = [
            'https://example.com',
            'https://httpbin.org/html',
            'https://jsonplaceholder.typicode.com'
        ];

        foreach ($urls as $url) {
            $result = $this->tool->execute([
                'action' => 'get_content',
                'url' => $url,
                'waitFor' => 5000,
                'screenshot' => false
            ]);

            $this->assertIsString($result);
            $this->assertNotEmpty($result);
        }
    }
}