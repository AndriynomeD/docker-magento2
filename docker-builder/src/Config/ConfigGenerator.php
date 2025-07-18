<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

use DockerBuilder\Core\Util\ArrayUtil;

/**
 * Configuration Generator
 */
class ConfigGenerator implements ConfigGeneratorInterface
{
    const DEFAULT_COMPOSER1VERSION = '1.10.17';
    const DEFAULT_XDEBUG2VERSION = '2.9.8';

    /**
     * Generate configuration from loaded config
     * @param array $config
     * @return array
     */
    public function generate(array $config): array
    {
        $config['general-config'] = $config['general-config'] ?? [];
        $config['php-containers'] = $config['php-containers'] ?? [];
        $defaultConfig = $this->getDefaultGeneralConfig();
        $config['general-config'] = ArrayUtil::arrayMergeRecursiveDistinct($defaultConfig, $config['general-config']);
        $config['general-config'] = $this->transformGeneralConfig($config['general-config']);
        $phpContainersConfig = $this->getActivePhpContainersConfig(
            $config['php-containers'],
            $config['general-config']
        );
        $config['php-containers'] = $this->transformPhpContainersConfig(
            $phpContainersConfig,
            $config['general-config']
        );

        return $config;
    }

    /**
     * Get active PHP containers configuration (фільтрація)
     * @param array $allPhpContainersConfig
     * @param array $generalConfig
     * @return array
     */
    private function getActivePhpContainersConfig(array $allPhpContainersConfig, array $generalConfig): array
    {
        $buildPhpVersion = $generalConfig['PHP_VERSION'];
        $needMcsPhpContainer = $generalConfig['DOCKER_SERVICES']['magento-coding-standard'] ?? false;

        return array_filter(
            $allPhpContainersConfig,
            function ($containerConfig, $name) use ($buildPhpVersion, $needMcsPhpContainer) {
                return ($containerConfig['version'] ?? '') === $buildPhpVersion
                    && (!str_contains($name, 'mcs') || $needMcsPhpContainer);
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Transform general configuration
     * @param array $generalConfig
     * @return array
     */
    private function transformGeneralConfig(array $generalConfig): array
    {
        // Handle search engine configuration
        if (is_array($generalConfig['DOCKER_SERVICES']['search_engine'])
            && $generalConfig['DOCKER_SERVICES']['search_engine']['CONNECT_TYPE'] == 'none') {
            $generalConfig['DOCKER_SERVICES']['search_engine'] = false;
        }

        // Set search engine availability
        $generalConfig['M2_SETTINGS']['SEARCH_ENGINE_AVAILABLE'] =
            $generalConfig['DOCKER_SERVICES']['search_engine'] !== false
            && in_array($generalConfig['DOCKER_SERVICES']['search_engine']['CONNECT_TYPE'], ['internal', 'external']);

        if ($generalConfig['DOCKER_SERVICES']['venia'] ?? false) {
            if (($generalConfig['M2_INSTALL']['USE_SAMPLE_DATA'] ?? 'false') !== 'false') {
                $generalConfig['M2_INSTALL']['USE_SAMPLE_DATA'] = 'venia';
            }
        }

        return $generalConfig;
    }

    /**
     * Transform PHP containers configuration
     * @param array $phpContainersConfig
     * @param array $generalConfig
     * @return array
     */
    private function transformPhpContainersConfig(array $phpContainersConfig, array $generalConfig): array
    {
        foreach ($phpContainersConfig as $name => &$containerConfig) {
            $containerConfig = $this->transformPhpContainer($containerConfig, $generalConfig);
        }

        return $phpContainersConfig;
    }

    /**
     * Transform single PHP container configuration
     * @param array $containerConfig
     * @param array $generalConfig
     * @return array
     */
    private function transformPhpContainer(array $containerConfig, array $generalConfig): array
    {
        /** set used in php container variables */
        $containerConfig['M2_EDITION'] = $generalConfig['M2_EDITION'];
        $containerConfig['phpVersion'] = $containerConfig['version'];
        $containerConfig['databaseType'] = $generalConfig['DOCKER_SERVICES']['database']['TYPE'];
        $containerConfig['databaseVersion'] = $generalConfig['DOCKER_SERVICES']['database']['VERSION'];

        if (isset($containerConfig['composerVersion']) && $containerConfig['composerVersion'] === 'latest') {
            $containerConfig['composerVersion'] = $this->resolveComposerVersion($generalConfig);
        }

        if (isset($containerConfig['xdebugVersion']) && $containerConfig['xdebugVersion'] === 'latest') {
            $containerConfig['xdebugVersion'] = $this->resolveXdebugVersion($generalConfig);
        }

        return $containerConfig;
    }

    /**
     * Resolve Composer version
     * @param array $generalConfig
     * @return string
     */
    private function resolveComposerVersion(array $generalConfig): string
    {
        if ($generalConfig['M2_EDITION'] === 'mage-os') {
            return 'latest';
        }

        $magentoVersion = str_replace('*', '9', $generalConfig['M2_VERSION']);
        if (version_compare($magentoVersion, '2.4.0', '>=')
            || (version_compare($magentoVersion, '2.3.7', '>=') && version_compare($magentoVersion, '2.4.0', '<'))
        ) {
            return 'latest';
        }

        return self::DEFAULT_COMPOSER1VERSION;
    }

    /**
     * Resolve XDebug version
     * @param array $generalConfig
     * @return string
     */
    private function resolveXdebugVersion(array $generalConfig): string
    {
        if ($generalConfig['M2_EDITION'] === 'mage-os') {
            return 'latest';
        }

        $magentoVersion = str_replace('*', '9', $generalConfig['M2_VERSION']);
        if (version_compare($magentoVersion, '2.3.7', '>=') &&
            version_compare($generalConfig['PHP_VERSION'], '7.1', '>=')) {
            return 'latest';
        }

        return self::DEFAULT_XDEBUG2VERSION;
    }

    /**
     * Get default general configuration
     * @return array
     */
    private function getDefaultGeneralConfig(): array
    {
        return [
            'M2_INSTALL' => [],
            'M2_SETTINGS' => [],
            'DOCKER_SERVICES' => [
                'database' => [],
                'search_engine' => false,
                'varnish' => false,
                'cron' => false,
                'redis' => false,
                'rabbitmq' => false,
                'magento-coding-standard' => false,
                'venia' => false
            ],
        ];
    }
}
