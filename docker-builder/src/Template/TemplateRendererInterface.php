<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Template;

/**
 * Interface for template rendering
 */
interface TemplateRendererInterface
{
    /**
     * Render template with variables
     * @param string $templatePath
     * @param array $variables
     * @return string
     */
    public function render(string $templatePath, array $variables): string;

    /**
     * Find a template file
     * @param string $filename
     * @param array $config
     * @return string|null
     */
    public function findTemplate(string $filename, array $config): ?string;
}
