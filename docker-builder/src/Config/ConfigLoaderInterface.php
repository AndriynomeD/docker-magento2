<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

use Exception;

/**
 * Interface for configuration loading
 */
interface ConfigLoaderInterface
{
    /**
     * Load configuration from a file
     * @param string $configFile
     * @return array
     * @throws Exception
     */
    public function loadConfig(string $configFile): array;
}
