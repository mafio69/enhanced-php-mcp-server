<?php

namespace App\Interfaces;

/**
 * Interface for template rendering
 *
 * Handles PHP template rendering with variables.
 */
interface TemplateRendererInterface
{
    public function render(string $templatePath, array $variables = []): string;
    public function exists(string $templatePath): bool;
}