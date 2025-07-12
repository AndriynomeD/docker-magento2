<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

use Exception;

/**
 * JSON Configuration Loader
 */
class JsonConfigLoader implements ConfigLoaderInterface
{
    /**
     * @param string $configFile
     * @return array
     * @throws Exception
     */
    public function loadConfig(string $configFile): array
    {
        if (!file_exists($configFile) || !is_readable($configFile)) {
            throw new Exception(sprintf("Configuration file %s not found or not readable", $configFile));
        }

        $content = file_get_contents($configFile);
        if ($content === false) {
            throw new Exception(sprintf("Failed to read configuration file %s", $configFile));
        }

        // Remove comments from JSON
//        $content = $this->removeJsonComments($content);

        $config = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception(sprintf("Invalid JSON in configuration file: %s", json_last_error_msg()));
        }

        if (!is_array($config)) {
            throw new Exception("Configuration must be an array");
        }

        return $config;
    }

    /**
     * Remove comments from JSON content
     * @param string $content
     * @return string
     */
    private function removeJsonComments(string $content): string
    {
        // Remove single line comments // ...
        $content = preg_replace('/\/\/.*$/m', '', $content);

        // Remove multi-line comments /* ... */
        $content = preg_replace('/\/\*.*?\*\//s', '', $content);

        return $content;
    }
}
