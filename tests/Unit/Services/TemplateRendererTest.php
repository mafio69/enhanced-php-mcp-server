<?php

namespace Tests\Unit\Services;

use App\Services\TemplateRenderer;
use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->renderer = new TemplateRenderer();
        $this->tempDir = sys_get_temp_dir() . '/template_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testExistsWithExistingTemplate(): void
    {
        $templateFile = $this->tempDir . '/test.php';
        file_put_contents($templateFile, 'Hello World');

        $this->assertTrue($this->renderer->exists($templateFile));
    }

    public function testExistsWithNonExistingTemplate(): void
    {
        $nonExistentFile = $this->tempDir . '/nonexistent.php';

        $this->assertFalse($this->renderer->exists($nonExistentFile));
    }

    public function testRenderWithVariables(): void
    {
        $templateFile = $this->tempDir . '/test.php';
        file_put_contents($templateFile, 'Hello <?= $name ?>!');

        $result = $this->renderer->render($templateFile, ['name' => 'World']);

        $this->assertEquals('Hello World!', $result);
    }

    public function testRenderWithoutVariables(): void
    {
        $templateFile = $this->tempDir . '/test.php';
        file_put_contents($templateFile, 'Static content');

        $result = $this->renderer->render($templateFile);

        $this->assertEquals('Static content', $result);
    }

    public function testRenderWithComplexTemplate(): void
    {
        $templateFile = $this->tempDir . '/complex.php';
        file_put_contents($templateFile,
            '<h1><?= $title ?></h1>' .
            '<p>Items:</p>' .
            '<?php foreach ($items as $item): ?>' .
            '<li><?= $item ?></li>' .
            '<?php endforeach; ?>'
        );

        $variables = [
            'title' => 'Test Page',
            'items' => ['Apple', 'Banana', 'Orange']
        ];

        $result = $this->renderer->render($templateFile, $variables);

        $this->assertStringContainsString('<h1>Test Page</h1>', $result);
        $this->assertStringContainsString('<li>Apple</li>', $result);
        $this->assertStringContainsString('<li>Banana</li>', $result);
        $this->assertStringContainsString('<li>Orange</li>', $result);
    }

    public function testRenderThrowsExceptionForNonExistentTemplate(): void
    {
        $nonExistentFile = $this->tempDir . '/nonexistent.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Template file not found: {$nonExistentFile}");

        $this->renderer->render($nonExistentFile);
    }
}