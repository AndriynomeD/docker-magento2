<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

use DockerBuilder\Core\Util\ArrayUtil;

/**
 * Configuration Generator
 */
class ConfigGenerator implements ConfigGeneratorInterface
{
    private const DEFAULT_COMPOSER1VERSION = '1.10.17';
    private const DEFAULT_XDEBUG2VERSION = '2.9.8';

    /**
     * Generate configuration from loaded config
     *
     * @param array $config
     * @return array
     */
    public function generate(array $config): array
    {
        $config['general-config'] = $config['general-config'] ?? [];
        $config['php-containers'] = $config['php-containers'] ?? [];

        $config['general-config'] = $this->transformGeneralConfig($config['general-config']);
        $config['general-config'] = $this->transformMagentoSettingsConfig($config['general-config']);
        $config['general-config'] = $this->transformMagentoInstallConfig($config['general-config']);
        $config['general-config'] = $this->transformDatabaseService($config['general-config']);
        $config['general-config'] = $this->transformSearchEngineService($config['general-config']);
        $config['general-config'] = $this->transformVeniaService($config['general-config']);
        $config['general-config'] = $this->transformNewRelicService($config['general-config']);
        $config['php-containers'] = $this->transformPhpContainersConfig($config);

        return $config;
    }

    /**
     * Get active PHP containers configuration
     *
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
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformGeneralConfig(array $generalConfig): array
    {
        $defaultConfig = $this->getDefaultGeneralConfig();
        $generalConfig = ArrayUtil::arrayMergeRecursiveDistinct($defaultConfig, $generalConfig);

        return $generalConfig;
    }

    /**
     * Transform Magento settings configuration
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformMagentoSettingsConfig(array $generalConfig): array
    {
        /** Set search engine availability */
        $generalConfig['M2_SETTINGS']['SEARCH_ENGINE_AVAILABLE'] =
            $generalConfig['DOCKER_SERVICES']['search_engine'] !== false
            && in_array($generalConfig['DOCKER_SERVICES']['search_engine']['CONNECT_TYPE'], ['internal', 'external']);

        return $generalConfig;
    }

    /**
     * Transform Magento install configuration
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformMagentoInstallConfig(array $generalConfig): array
    {
        /** @TODO make installer optional  */
        // This method can be extended based on specific requirements

        return $generalConfig;
    }

    /**
     * Transform database service
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformDatabaseService(array $generalConfig): array
    {
        $serviceKey = 'database';

        if (!($generalConfig['DOCKER_SERVICES'][$serviceKey]['IMAGE'] ?? false)) {
            $image = '';
            switch($generalConfig['DOCKER_SERVICES'][$serviceKey]['TYPE']) {
                case 'mariadb':
                    $image = 'mariadb';
                    break;
                case 'mysql':
                    $image = 'mysql';
                    break;
                case 'percona':
                    $image = 'percona';
                    break;
            }
            $generalConfig['DOCKER_SERVICES'][$serviceKey]['IMAGE'] = $image;
        }
        if (!($generalConfig['DOCKER_SERVICES'][$serviceKey]['TAG'] ?? false)) {
            $tag = $generalConfig['DOCKER_SERVICES'][$serviceKey]['VERSION'];
            $generalConfig['DOCKER_SERVICES'][$serviceKey]['TAG'] = $tag;
        }

        return $generalConfig;
    }

    /**
     * Transform search engine service
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformSearchEngineService(array $generalConfig): array
    {
        $serviceKey = 'search_engine';
        $generalConfig = $this->transformDockerServiceOnOff($generalConfig, $serviceKey);

        if (!($generalConfig['DOCKER_SERVICES'][$serviceKey]['IMAGE'] ?? false)) {
            $image = '';
            switch($generalConfig['DOCKER_SERVICES'][$serviceKey]['TYPE']) {
                case 'elasticsearch':
                    $image = 'docker.elastic.co/elasticsearch/elasticsearch';
                    break;
                case 'opensearch':
                    $image = 'opensearchproject/opensearch';
                    break;
            }
            $generalConfig['DOCKER_SERVICES'][$serviceKey]['IMAGE'] = $image;
        }
        if (!($generalConfig['DOCKER_SERVICES'][$serviceKey]['TAG'] ?? false)) {
            $tag = $generalConfig['DOCKER_SERVICES'][$serviceKey]['VERSION'];
            $generalConfig['DOCKER_SERVICES'][$serviceKey]['TAG'] = $tag;
        }

        return $generalConfig;
    }

    /**
     * Transform venia service
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformVeniaService(array $generalConfig): array
    {
        $serviceKey = 'venia';
        $isServiceEnabled = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;

        if ($isServiceEnabled) {
            if (($generalConfig['M2_INSTALL']['USE_SAMPLE_DATA'] ?? 'false') !== 'false') {
                $generalConfig['M2_INSTALL']['USE_SAMPLE_DATA'] = 'venia';
            }
        }

        return $generalConfig;
    }

    /**
     * Transform New Relic service
     *
     * @param array $generalConfig
     * @return array
     */
    private function transformNewRelicService(array $generalConfig): array
    {
        $serviceKey = 'newrelic';
        $generalConfig = $this->transformDockerServiceOnOff($generalConfig, $serviceKey);

        return $generalConfig;
    }

    /**
     * Transform PHP containers configuration
     *
     * @param array $config
     * @return array
     */
    private function transformPhpContainersConfig(array $config): array
    {
        $generalConfig = $config['general-config'];
        $phpContainersConfig = $this->getActivePhpContainersConfig(
            $config['php-containers'],
            $config['general-config']
        );
        foreach ($phpContainersConfig as $name => &$containerConfig) {
            $containerConfig = $this->transformPhpContainer($name, $containerConfig, $generalConfig);
        }

        return $phpContainersConfig;
    }

    /**
     * Transform single PHP container configuration
     *
     * @param string $name
     * @param array $containerConfig
     * @param array $generalConfig
     * @return array
     */
    private function transformPhpContainer(string $name, array $containerConfig, array $generalConfig): array
    {
        /** set used in php container variables */
        $containerConfig['M2_EDITION'] = $generalConfig['M2_EDITION'];
        $containerConfig['phpVersion'] = $containerConfig['version'];
        $containerConfig['databaseType'] = $generalConfig['DOCKER_SERVICES']['database']['TYPE'];
        $containerConfig['databaseVersion'] = $generalConfig['DOCKER_SERVICES']['database']['VERSION'];

        $containerConfig = $this->resolveNewRelicConfig($name, $containerConfig, $generalConfig);

        if (isset($containerConfig['composerVersion']) && $containerConfig['composerVersion'] === 'latest') {
            $containerConfig['composerVersion'] = $this->resolveComposerVersion($generalConfig);
        }

        if (isset($containerConfig['xdebugVersion']) && $containerConfig['xdebugVersion'] === 'latest') {
            $containerConfig['xdebugVersion'] = $this->resolveXdebugVersion($generalConfig);
        }

        return $containerConfig;
    }

    /**
     * @param array $generalConfig
     * @param string $serviceKey
     * @return array
     */
    private function transformDockerServiceOnOff(array $generalConfig, string $serviceKey): array
    {
        $serviceConfig = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;
        $isServiceEnabled = $serviceConfig !== false && ($serviceConfig['enabled'] ?? true);

        if (!$isServiceEnabled) {
            $generalConfig['DOCKER_SERVICES'][$serviceKey] = false;
        }

        return $generalConfig;
    }

    /**
     * Resolve Composer version
     *
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
     * Resolve xDebug version
     *
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
     * Resolve NewRelic config
     *
     * @param string $name
     * @param array $containerConfig
     * @param array $generalConfig
     * @return array
     */
    private function resolveNewRelicConfig(string $name, array $containerConfig, array $generalConfig): array
    {
        if (str_contains($name, 'mcs')) {
            $containerConfig['specificPackages']['newrelic'] = false;
        } else {
            $newrelicConfig = $generalConfig['DOCKER_SERVICES']['newrelic'] ?? false;
            if ($newrelicConfig !== false) {
                $containerConfig['specificPackages']['newrelic'] = $containerConfig['specificPackages']['newrelic'] ?? true;
            } else {
                $containerConfig['specificPackages']['newrelic'] = false;
            }
        }

        return $containerConfig;
    }

    /**
     * Get default general configuration
     *
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
                'venia' => false,
                'newrelic' => false,
            ],
        ];
    }
}
