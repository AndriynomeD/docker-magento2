<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

/**
 * Interface for configuration generation
 */
interface ConfigGeneratorInterface
{
    /**
     * Generate configuration from loaded config
     * @param array $config
     * @return array
     */
    public function generate(array $config): array;
}
