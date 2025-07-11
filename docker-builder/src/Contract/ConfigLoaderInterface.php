<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Contract;

use Exception;

/**
 * Interface for configuration loading
 */
interface ConfigLoaderInterface
{
    /**
     * Load configuration from file
     * @param string $configFile
     * @return array
     * @throws Exception
     */
    public function loadConfig(string $configFile): array;
}
