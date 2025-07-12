<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Config;

use Exception;

/**
 * Configuration Validator
 */
class ConfigValidator implements ConfigValidatorInterface
{
    const GENERAL_CONFIG_KEY = 'general-config';
    const PHP_CONTAINERS_CONFIG_KEY = 'php-containers';

    /**
     * Validate entire configuration
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
        $this->validateVeniaService($generalConfig);
        $this->validatePhpContainers($config[self::PHP_CONTAINERS_CONFIG_KEY], $generalConfig);
    }

    /**
     * Validate configuration structure
     * @param array $config
     * @throws Exception
     */
    private function validateStructure(array $config): void
    {
        if (!array_key_exists(self::GENERAL_CONFIG_KEY, $config)) {
            throw new Exception("Missing required configuration section: " . self::GENERAL_CONFIG_KEY);
        }

        if (!array_key_exists(self::PHP_CONTAINERS_CONFIG_KEY, $config) ||
            !is_array($config[self::PHP_CONTAINERS_CONFIG_KEY])) {
            throw new Exception("Missing or invalid configuration section: " . self::PHP_CONTAINERS_CONFIG_KEY);
        }
    }

    /**
     * Validate general configuration
     * @param array $generalConfig
     * @throws Exception
     */
    public function validateGeneralConfig(array $generalConfig): void
    {
        $requiredVars = [
            'M2_PROJECT', 'M2_VIRTUAL_HOSTS', 'M2_DB_NAME',
            'PHP_VERSION', 'M2_EDITION', 'M2_VERSION', 'M2_SOURCE_VOLUME'
        ];

        foreach ($requiredVars as $variable) {
            if (empty($generalConfig[$variable])) {
                throw new Exception(sprintf('%s is required.', $variable));
            }
        }

        $availableEditions = ['community', 'enterprise', 'cloud', 'mage-os'];
        if (!in_array($generalConfig['M2_EDITION'], $availableEditions)) {
            throw new Exception(sprintf('Incorrect Edition: %s. Available: %s',
                $generalConfig['M2_EDITION'], implode(', ', $availableEditions)));
        }
    }

    /**
     * Validate Magento settings configuration
     * @param array $generalConfig
     * @throws Exception
     */
    public function validateMagentoSettingsConfig(array $generalConfig): void
    {
        // Additional validation for M2_SETTINGS if needed
        // This method can be extended based on specific requirements
    }

    /**
     * Validate Magento install configuration
     * @param array $generalConfig
     * @throws Exception
     */
    public function validateMagentoInstallConfig(array $generalConfig): void
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
     * @param array $generalConfig
     * @throws Exception
     */
    public function validateDatabaseService(array $generalConfig): void
    {
        if (!isset($generalConfig['DOCKER_SERVICES']['database'])) {
            throw new Exception('Database service configuration is required.');
        }

        $databaseConfig = $generalConfig['DOCKER_SERVICES']['database'];

        $requiredKeys = ['IMAGE', 'TYPE', 'VERSION', 'VOLUME'];
        foreach ($requiredKeys as $key) {
            if (empty($databaseConfig[$key])) {
                throw new Exception(sprintf('Database %s is required.', $key));
            }
        }

        $availableTypes = ['mariadb', 'mysql', 'percona'];
        if (!in_array($databaseConfig['TYPE'], $availableTypes)) {
            throw new Exception(sprintf('Available database types: %s', implode(', ', $availableTypes)));
        }
    }

    /**
     * Validate search engine service
     * @param array $generalConfig
     * @throws Exception
     */
    public function validateSearchEngineService(array $generalConfig): void
    {
        if (!isset($generalConfig['DOCKER_SERVICES']['search_engine'])) {
            return;
        }

        $searchEngineConfig = $generalConfig['DOCKER_SERVICES']['search_engine'];

        if ($searchEngineConfig === false) {
            return;
        }

        if (($searchEngineConfig['CONNECT_TYPE'] ?? '') === 'none') {
            $searchEngineConfig = false;
        }

        $searchEngineAvailable = $searchEngineConfig !== false
            && in_array($searchEngineConfig['CONNECT_TYPE'] ?? '', ['internal', 'external']);

        $magentoVersion = str_replace('*', '9', $generalConfig['M2_VERSION']);
        if (!$searchEngineAvailable && version_compare($magentoVersion, '2.4.0', '>=')) {
            throw new Exception('External or Internal Search Engine is required for magento 2.4.0+');
        }

        if (is_array($searchEngineConfig)
            && !in_array($searchEngineConfig['TYPE'] ?? '', ['elasticsearch', 'opensearch'])) {
            throw new Exception(sprintf('Available search engine types: %s',
                implode(', ', ['elasticsearch', 'opensearch'])));
        }
    }

    /**
     * Validate Venia service
     * @param array $generalConfig
     * @throws Exception
     */
    public function validateVeniaService(array $generalConfig): void
    {
        $services = $generalConfig['DOCKER_SERVICES'] ?? [];

        if (!($services['venia'] ?? false)) {
            return;
        }

        if ($services['varnish'] ?? false) {
            throw new Exception('Venia PWA does not need Varnish on Magento backend');
        }
    }

    /**
     * Validate PHP containers configuration
     * @param array $phpContainersConfig
     * @param array $generalConfig
     * @throws Exception
     */
    public function validatePhpContainers(array $phpContainersConfig, array $generalConfig): void
    {
        $phpContainersConfig = $this->getActivePhpContainersConfig($phpContainersConfig, $generalConfig);
        foreach ($phpContainersConfig as $name => $containerConfig) {
            $this->validatePhpContainer($name, $containerConfig, $generalConfig);
        }
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
     * Validate single PHP container
     * @param string $name
     * @param array $containerConfig
     * @param array $generalConfig
     * @throws Exception
     */
    private function validatePhpContainer(string $name, array $containerConfig, array $generalConfig): void
    {
        if (($containerConfig['specificPackages']['ioncube'] ?? false) &&
            version_compare($containerConfig['version'] ?? '', '8.0', '>=')) {
            throw new Exception(sprintf('PHP-%s: IonCube not supported for PHP 8.0 or higher.', $name));
        }

        if ($generalConfig['M2_EDITION'] === 'cloud') {
            if (!isset($containerConfig['specificPackages']) ||
                ($containerConfig['specificPackages']['calendar'] ?? false) === false) {
                throw new Exception(sprintf(
                    'ext-calendar is required for \'Cloud\' edition. Please enable specificPackages/calendar for PHP %s.',
                    $generalConfig['PHP_VERSION']
                ));
            }
        }
    }
}
