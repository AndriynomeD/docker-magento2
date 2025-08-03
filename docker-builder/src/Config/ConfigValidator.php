<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

use Exception;

/**
 * Configuration Validator
 */
class ConfigValidator implements ConfigValidatorInterface
{
    private const GENERAL_CONFIG_KEY = 'general-config';
    private const PHP_CONTAINERS_CONFIG_KEY = 'php-containers';

    /**
     * Validate entire configuration
     *
     * @param array $config
     * @throws Exception
     */
    public function validate(array $config): void
    {
        $this->validateStructure($config);

        $generalConfig = $config[self::GENERAL_CONFIG_KEY];

        $this->validateGeneralConfig($generalConfig);
        $this->validateMagentoSettingsConfig($generalConfig);
        $this->validateMagentoInstallConfig($generalConfig);
        $this->validateDatabaseService($generalConfig);
        $this->validateSearchEngineService($generalConfig);
        $this->validateRedisService($generalConfig);
        $this->validateVeniaService($generalConfig);
        $this->validateNewRelicService($generalConfig);
        $this->validatePhpContainers($config[self::PHP_CONTAINERS_CONFIG_KEY], $generalConfig);
    }

    /**
     * Validate configuration structure
     *
     * @param array $config
     * @throws Exception
     */
    private function validateStructure(array $config): void
    {
        if (!array_key_exists(self::GENERAL_CONFIG_KEY, $config)
            || !is_array($config[self::GENERAL_CONFIG_KEY])) {
            throw new Exception("Missing or invalid configuration section: " . self::GENERAL_CONFIG_KEY);
        }

        if (!array_key_exists(self::PHP_CONTAINERS_CONFIG_KEY, $config) ||
            !is_array($config[self::PHP_CONTAINERS_CONFIG_KEY])) {
            throw new Exception("Missing or invalid configuration section: " . self::PHP_CONTAINERS_CONFIG_KEY);
        }

        $requiredVars = [
            'M2_PROJECT', 'M2_VIRTUAL_HOSTS', 'M2_DB_NAME',
            'PHP_VERSION', 'M2_EDITION', 'M2_VERSION', 'M2_SOURCE_VOLUME', 'DOCKER_SERVICES'
        ];

        foreach ($requiredVars as $variable) {
            if (empty($config[self::GENERAL_CONFIG_KEY][$variable])) {
                throw new Exception(sprintf('%s is required.', $variable));
            }
        }

        if (!is_array($config[self::GENERAL_CONFIG_KEY]['DOCKER_SERVICES'])) {
            throw new Exception('DOCKER_SERVICES must be array');
        }
    }

    /**
     * Validate general configuration
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateGeneralConfig(array $generalConfig): void
    {
        $availableEditions = ['community', 'enterprise', 'cloud', 'mage-os'];
        if (!in_array($generalConfig['M2_EDITION'], $availableEditions)) {
            throw new Exception(sprintf('Incorrect Edition: %s. Available: %s',
                $generalConfig['M2_EDITION'], implode(', ', $availableEditions)));
        }
    }

    /**
     * Validate Magento settings configuration
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateMagentoSettingsConfig(array $generalConfig): void
    {
        // Additional validation for M2_SETTINGS if needed
        // This method can be extended based on specific requirements
    }

    /**
     * Validate Magento install configuration
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateMagentoInstallConfig(array $generalConfig): void
    {
        /** @TODO make installer optional  */
//        if (!isset($generalConfig['M2_INSTALL'])) {
//            return;
//        }

        $installConfig = $generalConfig['M2_INSTALL'];

        if ($generalConfig['M2_EDITION'] === 'cloud') {
            if (($installConfig['INSTALL_DB'] ?? '') !== 'false') {
                throw new Exception('INSTALL_DB not available for \'Cloud\' edition.');
            }
            if (($installConfig['USE_SAMPLE_DATA'] ?? '') !== 'false') {
                throw new Exception('USE_SAMPLE_DATA not available for \'Cloud\' edition.');
            }
        }
    }

    /**
     * Validate database service
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateDatabaseService(array $generalConfig): void
    {
        $serviceName = 'Database';
        $serviceKey = 'database';
        $isServiceEnabled = $this->validateDockerService(
            $generalConfig,
            $serviceName,
            $serviceKey,
            true,
            ['TYPE', 'VERSION', 'VOLUME']
        );

        if (!$isServiceEnabled) {
            return;
        }

        $serviceConfig = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;
        $availableTypes = ['mariadb', 'mysql', 'percona'];
        if (!in_array($serviceConfig['TYPE'], $availableTypes)) {
            throw new Exception(sprintf('Service %s: Available types: %s',
                $serviceName, implode(', ', $availableTypes)));
        }
    }

    /**
     * Validate search engine service
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateSearchEngineService(array $generalConfig): void
    {
        $serviceName = 'Search Engine';
        $serviceKey = 'search_engine';
        $magentoVersion = str_replace('*', '9', $generalConfig['M2_VERSION']);
        $isServiceRequired = version_compare($magentoVersion, '2.4.0', '>=');
        $isServiceEnabled = $this->validateDockerService(
            $generalConfig,
            $serviceName,
            $serviceKey,
            $isServiceRequired,
            ['enabled', 'CONNECT_TYPE', 'TYPE', 'VERSION'],
            'Service %s is required for Magento 2.4.0+'
        );

        if (!$isServiceEnabled) {
            return;
        }

        $serviceConfig = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;
        $availableTypes = ['elasticsearch', 'opensearch'];
        if (!in_array($serviceConfig['TYPE'], $availableTypes)) {
            throw new Exception(sprintf('Service %s: Available types: %s',
                $serviceName, implode(', ', $availableTypes)));
        }
    }

    /**
     * Validate redis service
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateRedisService(array $generalConfig): void
    {
        $serviceName = 'In-memory datastore';
        $serviceKey = 'redis';
        $isServiceEnabled = $this->validateDockerService(
            $generalConfig,
            $serviceName,
            $serviceKey,
            false,
            ['enabled', 'TYPE', 'VERSION']
        );

        if (!$isServiceEnabled) {
            return;
        }

        $serviceConfig = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;
        $availableTypes = ['redis', 'valkey'];
        if (!in_array($serviceConfig['TYPE'], $availableTypes)) {
            throw new Exception(sprintf('Service %s: Available types: %s',
                $serviceName, implode(', ', $availableTypes)));
        }
    }

    /**
     * Validate Venia service
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateVeniaService(array $generalConfig): void
    {
        $serviceName = 'Venia';
        $serviceKey = 'venia';
        $isServiceEnabled = $this->validateDockerServiceSimple(
            $generalConfig,
            $serviceName,
            $serviceKey,
            false
        );

        if (!$isServiceEnabled) {
            return;
        }

        throw new Exception('Venia PWA does not need Varnish on Magento backend');
    }

    /**
     * Validate New Relic service
     *
     * @param array $generalConfig
     * @throws Exception
     */
    private function validateNewRelicService(array $generalConfig): void
    {
        $serviceName = 'New Relic';
        $serviceKey = 'newrelic';
        $isServiceEnabled = $this->validateDockerService(
            $generalConfig,
            $serviceName,
            $serviceKey,
            false,
            ['enabled', 'infrastructure']
        );

        if (!$isServiceEnabled) {
            return;
        }

        throw new Exception('New Relic is not supported yet.');
    }

    /**
     * Validate PHP containers configuration
     *
     * @param array $phpContainersConfig
     * @param array $generalConfig
     * @throws Exception
     */
    private function validatePhpContainers(array $phpContainersConfig, array $generalConfig): void
    {
        $phpContainersConfig = $this->getActivePhpContainersConfig($phpContainersConfig, $generalConfig);
        foreach ($phpContainersConfig as $name => $containerConfig) {
            $this->validatePhpContainer($name, $containerConfig, $generalConfig);
        }
    }

    /**
     * Docker Service config type:
     *      $isServiceRequired==false => false|{}|{'enabled' => false|true}
     *      $isServiceRequired==true => {}|{'enabled' => true}
     *
     * @param array $generalConfig
     * @param string $serviceName
     * @param string $serviceKey
     * @param bool $isServiceRequired
     * @param array $requiredKeys
     * @param string $requiredMsg
     * @return bool
     * @throws Exception
     */
    private function validateDockerService(
        array  $generalConfig,
        string $serviceName,
        string $serviceKey,
        bool   $isServiceRequired,
        array  $requiredKeys = [],
        string $requiredMsg = 'Service %s is required.'
    ): bool {
        $serviceConfig = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;
        if ($serviceConfig !== false && !is_array($serviceConfig)) {
            throw new Exception(sprintf('Service %s: bad configuration.', $serviceName));
        }

        $isServiceEnabled = $serviceConfig !== false && ($serviceConfig['enabled'] ?? true);
        if (!$isServiceRequired && !$isServiceEnabled) {
            return false;
        } elseif ($isServiceRequired && !$isServiceEnabled) {
            throw new Exception(sprintf($requiredMsg, $serviceName));
        }

        foreach ($requiredKeys as $key) {
            if (empty($serviceConfig[$key])) {
                throw new Exception(sprintf('Service %s: %s is required.', $serviceName, $key));
            }
        }

        return $isServiceEnabled;
    }

    /**
     * Docker Service config type:
     *      $isServiceRequired==false => false|false
     *      $isServiceRequired==true => true
     *
     * @param array $generalConfig
     * @param string $serviceName
     * @param string $serviceKey
     * @param bool $isServiceRequired
     * @param string $requiredMsg
     * @return bool
     * @throws Exception
     */
    private function validateDockerServiceSimple(
        array  $generalConfig,
        string $serviceName,
        string $serviceKey,
        bool   $isServiceRequired,
        string $requiredMsg = 'Service %s is required.'
    ): bool {
        $serviceConfig = $generalConfig['DOCKER_SERVICES'][$serviceKey] ?? false;
        if (!is_bool($serviceConfig)) {
            throw new Exception(sprintf('Service %s: bad configuration.', $serviceName));
        }

        $isServiceEnabled = $serviceConfig;
        if (!$isServiceRequired && !$isServiceEnabled) {
            return false;
        } elseif ($isServiceRequired && !$isServiceEnabled) {
            throw new Exception(sprintf($requiredMsg, $serviceName));
        }

        return $isServiceEnabled;
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
     * Validate single PHP container
     * @param string $name
     * @param array $containerConfig
     * @param array $generalConfig
     * @throws Exception
     */
    private function validatePhpContainer(string $name, array $containerConfig, array $generalConfig): void
    {
        if ($containerConfig['flavour'] !== 'cli') {
            if ($containerConfig['specificPackages']['grunt'] ?? false) {
                throw new Exception(sprintf('PHP-%s: Grunt available only for cli containers.', $name));
            }
        }
        if (($containerConfig['specificPackages']['ioncube'] ?? false)
            && (version_compare($containerConfig['version'] ?? '', '8.0', '=')
            || version_compare($containerConfig['version'] ?? '', '8.4', '>='))) {
            throw new Exception(
                sprintf('PHP-%s: Ioncube not support this php version or support will be added later.', $name)
            );
        }
        if (isset($containerConfig['specificPackages']['newrelic'])
            && !is_string($containerConfig['specificPackages']['newrelic'])) {
            throw new Exception(
                sprintf('PHP-%s: if specificPackages.newrelic set it must be string with specific version.' . PHP_EOL
                    . 'On/Off of newrelic saved in DOCKER_SERVICES.newrelic config', $name)
            );
        }

    }
}
