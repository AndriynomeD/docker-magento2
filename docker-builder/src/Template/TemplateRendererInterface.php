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
     * @param string $templateFilePath Relative file path from 'templates' folder
     * @param array $variables
     * @return string
     */
    public function render(string $templateFilePath, array $variables): string;

    /**
     * Return the first found template file name for the given file
     * @param string $filename
     * @param array $config
     * @return string|null
     */
    public function findTemplate(string $filename, array $config): ?string;
}
