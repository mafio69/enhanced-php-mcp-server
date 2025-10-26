<?php

namespace App\Services;

use App\Interfaces\TemplateRendererInterface;

/**
 * Template renderer service
 */
class TemplateRenderer implements TemplateRendererInterface
{
    public function render(string $templatePath, array $variables = []): string
    {
        if (!$this->exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }

        // Extract variables for use in template
        extract($variables);

        // Start output buffering
        ob_start();

        // Include the template
        include $templatePath;

        // Get the buffered content and clean buffer
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    public function exists(string $templatePath): bool
    {
        return file_exists($templatePath);
    }
}