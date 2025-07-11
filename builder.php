<?php

/**
 * Class ConfigBuilder
 *
 * Builds files from given configuration and source templates.
 */
class ConfigBuilder
{
    const CONFIG_FILE_NAME = 'config.json';
    const CONFIG_FILE = __DIR__ . DIRECTORY_SEPARATOR . self::CONFIG_FILE_NAME;
    const TEMPLATE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'src';
    const GENERAL_CONFIG_KEY = 'general-config';
    const PHP_CONTAINERS_CONFIG_KEY = 'php-containers';
    const CONTAINERS_BASE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'containers';
    const CONTAINERS_DRY_RUN_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'containers-dry-run';

    // Message types for colored output
    const MSG_ERROR = 'error';
    const MSG_WARNING = 'warning';
    const MSG_INFO = 'info';
    const MSG_SUCCESS = 'success';

    // Message colors
    const COLORS = [
        self::MSG_ERROR => "\033[1;37m\033[0;31m",
        self::MSG_WARNING => "\033[1;37m\033[1;33m",
        self::MSG_INFO => "\033[1;37m\033[1;34m",
        self::MSG_SUCCESS => "\033[1;37m\033[1;32m",
    ];
    const COLOR_RESET = "\033[0m";

    const DEFAULT_EXECUTABLE_PERMISSIONS = 0755;
    const DEFAULT_VERBOSE_LEVEL = 1;
    const DEFAULT_COMPOSER1VERSION = '1.10.17';
    const DEFAULT_XDEBUG2VERSION = '2.9.8';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var int
     */
    protected $executablePermissions;

    /**
     * @var int
     */
    protected $verboseLevel;

    /**
     * @var bool
     */
    protected $dryRun;

    /**
     * @var array
     */
    protected $generalConfig;

    /**
     * @param $options
     * @throws Exception
     */
    public function __construct($options = [])
    {
        $this->executablePermissions = $options['executable_file_permissions'] ?? static::DEFAULT_EXECUTABLE_PERMISSIONS;
        $this->verboseLevel = $options['verbose'] ?? static::DEFAULT_VERBOSE_LEVEL;
        $this->dryRun = $options['dry_run'] ?? false;
        if ($this->dryRun) {
            $this->verbose($this->formatMessage('DRY RUN MODE: Files will be created in separate directories for comparison', self::MSG_INFO), 1);
        }
        $this->loadConfig(static::CONFIG_FILE);
    }

    /**
     * @param string $file
     * @return $this
     * @throws Exception
     */
    protected function loadConfig($file)
    {
        if (!(file_exists($file) && is_readable($file))) {
            $this->throwError(sprintf("File %s not exist or not readable!", self::CONFIG_FILE_NAME));
        }

        $config = json_decode(file_get_contents($file), true);

        if (!is_array($config)
            || !array_key_exists(static::GENERAL_CONFIG_KEY, $config)
            || (!array_key_exists(static::PHP_CONTAINERS_CONFIG_KEY, $config)
                || !is_array($config[static::PHP_CONTAINERS_CONFIG_KEY]))
        ) {
            $this->throwError(sprintf("Invalid configuration in %s!", $file));
        }

        $this->config = $config;
        return $this;
    }

//#==============================================================================
//# BLOCK: Base helper logic
//#==============================================================================

    /**
     * @param array ...$arrays
     * @return array
     */
    protected function array_merge_recursive_distinct(array ...$arrays): array
    {
        $base = array_shift($arrays);

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                    $base[$key] = $this->array_merge_recursive_distinct($base[$key], $value);
                } else {
                    $base[$key] = $value;
                }
            }
        }

        return $base;
    }

//#==============================================================================
//# BLOCK: error/massage helper logic
//#==============================================================================

    /**
     * Throw formatted error
     */
    protected function throwError($message)
    {
        throw new Exception($this->formatMessage($message, self::MSG_ERROR));
    }

    /**
     * Show formatted warning
     */
    protected function showWarning($message)
    {
        $this->verbose($this->formatMessage($message, self::MSG_WARNING), 1);
    }

    /**
     * Format message with colors
     */
    protected function formatMessage($message, $type = self::MSG_INFO)
    {
        $color = self::COLORS[$type] ?? self::COLORS[self::MSG_INFO];
        return $color . $message . self::COLOR_RESET;
    }

    /**
     * Print an informational message to the command line.
     *
     * @param string $message
     * @param int    $level
     * @param bool   $newline
     * @return $this
     */
    protected function verbose($message, $level = 1, $newline = true)
    {
        if ($level <= $this->verboseLevel) {
            printf("%s%s", $message, $newline ? PHP_EOL : "");
        }
        return $this;
    }

    /**
     * @return string
     */
    protected function getContainersBaseDir()
    {
        return $this->dryRun ? self::CONTAINERS_DRY_RUN_DIR : self::CONTAINERS_BASE_DIR;
    }

    /**
     * Get the compose file name based on dry run mode
     *
     * @return string
     */
    protected function getComposeFileName()
    {
        return $this->dryRun ? 'compose-dry-run.yaml' : 'compose.yaml';
    }

    /**
     * @param $generalConfig
     * @return mixed
     */
    protected function getActivePhpContainersConfig($generalConfig)
    {
        $buildPhpVersion = $generalConfig['PHP_VERSION'];
        $needMCSphpContainer = $generalConfig['DOCKER_SERVICES']['magento-coding-standard'];
        $allPhpContainersConfig = $this->config[self::PHP_CONTAINERS_CONFIG_KEY];

        $phpContainersConfig = array_filter(
            $allPhpContainersConfig,
            function($containerConfig, $name) use ($buildPhpVersion, $needMCSphpContainer) {
                return $containerConfig['version'] === $buildPhpVersion
                    && (!str_contains($name, 'mcs') || $needMCSphpContainer);
            },
            ARRAY_FILTER_USE_BOTH
        );

        return $phpContainersConfig;
    }

//#==============================================================================
//# BLOCK: file/template helper logic
//#==============================================================================



//#==============================================================================
//# BLOCK: Main logic
//#==============================================================================

    /**
     * Build the docker infrastructure based on the loaded config.
     */
    public function run()
    {
        $this->validateConfiguration();
        $this->buildContainers();

        if ($this->dryRun) {
            $this->verbose($this->formatMessage('DRY RUN COMPLETED', self::MSG_SUCCESS), 1);
            $this->verbose($this->formatMessage('Check the following for comparison:', self::MSG_INFO), 1);
            $this->verbose($this->formatMessage('- Containers: ' . $this->getContainersBaseDir(), self::MSG_INFO), 1);
            $this->verbose($this->formatMessage('- Compose file: ' . $this->getComposeFileName(), self::MSG_INFO), 1);
        }
    }

//#==============================================================================
//# BLOCK: Validation
//#==============================================================================

    /**
     * Validate entire configuration
     */
    protected function validateConfiguration()
    {
        $this->validateGeneralConfig();
        $this->validateMagentoSettingsConfig();
        $this->validateMagentoInstallConfig();

        $this->validateDatabaseService();
        $this->validateSearchEngineService();
        $this->validateVeniaService();
        $this->validatePhpContainersConfig();
    }

    /**
     * Validate general configuration
     */
    protected function validateGeneralConfig()
    {
        $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];

        $requiredVars = ['M2_PROJECT', 'M2_VIRTUAL_HOSTS', 'M2_DB_NAME', 'PHP_VERSION', 'M2_EDITION', 'M2_VERSION', 'M2_SOURCE_VOLUME'];
        foreach ($requiredVars as $variable) {
            if (empty($generalConfig[$variable])) {
                $this->throwError(sprintf('%s is required.', $variable));
            }
        }

        $availableEditions = ['community', 'enterprise', 'cloud', 'mage-os'];
        if (!in_array($generalConfig['M2_EDITION'], $availableEditions)) {
            $this->throwError(sprintf('Incorrect Edition: %s. Available: %s',
                $generalConfig['M2_EDITION'], implode(', ', $availableEditions)));
        }

        /** @deprecated: remove in feature releases */
//        if ($generalConfig['HTTPS_HOST'] && !$generalConfig['NGINX_PROXY_PATH']) {
//            $this->throwError('Https required `NGINX_PROXY_PATH`');
//        }
    }

    /**
     * Validate Magento install configuration
     */
    protected function validateMagentoInstallConfig()
    {
        $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
        $installConfig = $this->config[self::GENERAL_CONFIG_KEY]['M2_INSTALL'];

        if ($generalConfig['M2_EDITION'] === 'cloud') {
            if ($installConfig['INSTALL_DB'] !== 'false') {
                $this->throwError('INSTALL_DB not available for \'Cloud\' edition.');
            }
            if ($installConfig['USE_SAMPLE_DATA'] !== 'false') {
                $this->throwError('USE_SAMPLE_DATA not available for \'Cloud\' edition.');
            }
        }
    }

    /**
     * Validate Magento settings configuration
     */
    protected function validateMagentoSettingsConfig()
    {
        // Additional validation for M2_SETTINGS if needed
        // This method can be extended based on specific requirements
    }


    /**
     * Validate database service
     */
    protected function validateDatabaseService()
    {
        $services = $this->config[self::GENERAL_CONFIG_KEY]['DOCKER_SERVICES'];
        $databaseConfig = $services['database'];

        $requiredKeys = ['IMAGE', 'TYPE', 'VERSION', 'VOLUME'];
        foreach ($requiredKeys as $key) {
            if (empty($databaseConfig[$key])) {
                $this->throwError(sprintf('Database %s is required.', $key));
            }
        }

        $availableTypes = ['mariadb', 'mysql', 'percona'];
        if (!in_array($databaseConfig['TYPE'], $availableTypes)) {
            $this->throwError(sprintf('Available database types: %s', implode(', ', $availableTypes)));
        }
    }

    /**
     * Validate search engine service
     */
    protected function validateSearchEngineService()
    {
        $services = $this->config[self::GENERAL_CONFIG_KEY]['DOCKER_SERVICES'];
        $searchEngineConfig = $services['search_engine'];

        if ($searchEngineConfig !== false) {
            if ($searchEngineConfig['CONNECT_TYPE'] === 'none') {
                $searchEngineConfig = false;
            }

            $searchEngineAvailable = $searchEngineConfig !== false
                && in_array($searchEngineConfig['CONNECT_TYPE'], ['internal', 'external']);
            $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
            $magentoVersion = str_replace('*', 9, $generalConfig['M2_VERSION']);
            if (!$searchEngineAvailable && version_compare($magentoVersion, '2.4.0', '>=')) {
                $this->throwError('External or Internal Search Engine is required for magento 2.4.0+');
            }

            if (is_array($searchEngineConfig)
                && !in_array($searchEngineConfig['TYPE'], ['elasticsearch', 'opensearch'])) {
                $this->throwError(sprintf('Available search engine: %s',
                    implode(',', ['elasticsearch', 'opensearch'])));
            }
        }
    }

    /**
     * Validate Venia service
     */
    protected function validateVeniaService()
    {
        $services = $this->config[self::GENERAL_CONFIG_KEY]['DOCKER_SERVICES'];

        if ($services['venia']) {
            if ($services['varnish']) {
                $this->throwError('Venia PWA not need Varnish on Magento backend');
            }

//            $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
//            if (!$generalConfig['HTTPS_HOST']) {
//                $this->throwError('Venia PWA required `HTTPS_HOST`');
//            }

            // in transformGeneralConfig
//            $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
//            if ($generalConfig['M2_INSTALL']['USE_SAMPLE_DATA'] &&
//                $generalConfig['M2_INSTALL']['USE_SAMPLE_DATA'] !== 'false') {
//                // Auto-set to venia sample data
//                $this->config[self::GENERAL_CONFIG_KEY]['M2_INSTALL']['USE_SAMPLE_DATA'] = 'venia';
//            }
        }
    }

    /**
     * Validate PHP containers configuration
     */
    protected function validatePhpContainersConfig()
    {
        $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
        $phpContainersConfig = $this->getActivePhpContainersConfig($generalConfig);
        foreach ($phpContainersConfig as $name => $containerConfig) {
            $this->validatePhpContainer($name, $containerConfig, $generalConfig);
        }
    }

    /**
     * Validate single PHP container
     */
    protected function validatePhpContainer($name, $containerConfig, $generalConfig)
    {
        if ($containerConfig['specificPackages']['ioncube'] &&
            version_compare($containerConfig['version'], '8.0', '>=')) {
            $this->throwError(sprintf('PHP-%s: %s', $name, 'IonCube not supported PHP 8 or higher yet.'));
        }

        if ($generalConfig['M2_EDITION'] === 'cloud') {
            if (!isset($containerConfig['specificPackages']) ||
                $containerConfig['specificPackages']['calendar'] === false) {
                $this->throwError(sprintf(
                    'ext-calendar is required for \'Cloud\' edition. Please enable specificPackages/calendar for PHP %s.',
                    $generalConfig['PHP_VERSION']
                ));
            }
        }
    }

//#==============================================================================
//# BLOCK: Build infrastructure logic
//#==============================================================================

    /**
     * Build all containers
     */
    protected function buildContainers()
    {
        $this->buildPhpContainers();
        $this->buildNginxContainer();
        $this->buildSearchEngineContainer();
        $this->buildDockerCompose();
    }

    /**
     * Build PHP containers (refactored)
     */
    protected function buildPhpContainers()
    {
        $generalConfig = $this->getGeneralConfig();
        $phpContainersConfig = $this->getActivePhpContainersConfig($generalConfig);

        foreach ($phpContainersConfig as $name => $containerConfig) {
            $containerConfig['templateDir'] = self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'phpContainers' . DIRECTORY_SEPARATOR;
            $containerConfig['destinationDir'] = $this->getContainersBaseDir() . DIRECTORY_SEPARATOR . 'php';

            /** set used in php container variables */
            $containerConfig['phpVersion'] = $containerConfig['version'];
            $containerConfig['databaseType'] = $generalConfig['DOCKER_SERVICES']['database']['TYPE'];
            $containerConfig['databaseVersion'] = $generalConfig['DOCKER_SERVICES']['database']['VERSION'];

            if (isset($containerConfig['composerVersion']) && $containerConfig['composerVersion'] === 'latest') {
                if ($generalConfig['M2_EDITION'] === 'mage-os') {
                    $containerConfig['composerVersion'] = 'latest';
                } else {
                    $magentoVersion = str_replace('*', 9, $generalConfig['M2_VERSION']);
                    if (version_compare($magentoVersion, '2.4.2', '>=')) {
                        $containerConfig['composerVersion'] = 'latest';
                    } elseif (version_compare($magentoVersion, '2.3.7', '>=')) {
                        $containerConfig['composerVersion'] = 'latest';
                    } else {
                        $containerConfig['composerVersion'] = self::DEFAULT_COMPOSER1VERSION;
                    }
                }
            }

            if (isset($containerConfig['xdebugVersion']) && $containerConfig['xdebugVersion'] == 'latest') {
                if ($generalConfig['M2_EDITION'] === 'mage-os') {
                    $containerConfig['composerVersion'] = 'latest';
                } else {
                    $magentoVersion = str_replace('*', 9, $generalConfig['M2_VERSION']);
                    if (version_compare($magentoVersion, '2.3.7', '>=')
                        && version_compare($generalConfig['PHP_VERSION'], '7.1', '>=')
                    ) {
                        $containerConfig['xdebugVersion'] = 'latest';
                    } else {
                        $containerConfig['xdebugVersion'] = self::DEFAULT_XDEBUG2VERSION;
                    }
                }
            }

            $this->buildContainer($name, $containerConfig);
        }
    }

    // Keep existing methods but clean them up
    protected function buildNginxContainer()
    {
        // Currently only generate ssl certificates - can be extended
        $generalConfig = $this->getGeneralConfig();
        // SSL generation logic here if needed
    }

    protected function buildSearchEngineContainer()
    {
        $generalConfig = $this->getGeneralConfig();
        $isSearchEngineInternal = $generalConfig['DOCKER_SERVICES']['search_engine'] !== false
            && $generalConfig['DOCKER_SERVICES']['search_engine']['CONNECT_TYPE'] == 'internal';

        if ($isSearchEngineInternal) {
            $searchEngineType = $generalConfig['DOCKER_SERVICES']['search_engine']['TYPE'];
            $searchEngineVersion = $generalConfig['DOCKER_SERVICES']['search_engine']['VERSION'];
            $containerConfig = [
                'version' => $searchEngineVersion,
                'context-folder' => $searchEngineType,
                'files' => [
                    'Dockerfile' => ['_enable_variables' => true]
                ]
            ];

            $containerConfig['templateDir'] = self::TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'search_engine' . DIRECTORY_SEPARATOR . $searchEngineType. DIRECTORY_SEPARATOR;
            $containerConfig['destinationDir'] = $this->getContainersBaseDir() . DIRECTORY_SEPARATOR . 'search_engine';
            $containerConfig['ELASTICSEARCH_VERSION'] = $searchEngineType == 'elasticsearch' ? $searchEngineVersion: '';
            $containerConfig['OPENSEARCH_VERSION'] = $searchEngineType == 'opensearch' ? $searchEngineVersion: '';


            $this->buildContainer('search_engine', $containerConfig);
        }
    }

    protected function buildDockerCompose()
    {
        $generalConfig = $this->getGeneralConfig();
        $this->verbose(sprintf("Building '%s'...", $this->getComposeFileName()), 1);

        $templateConfig = [
            'templateDirPath' => self::TEMPLATE_DIR . DIRECTORY_SEPARATOR,
            'version' => '',
            'flavour' => '',
            'templateSuffix' => ''
        ];

        $filename = 'compose-template.php';
        $fileVariables = ['_enable_variables' => true, 'executable' => true];
        /** buildComposeFile inspired buildContainerFile */
//        $destinationDir = $containerConfig['destinationDir']; // __DIR__ . DIRECTORY_SEPARATOR
        $templateFile = $this->getTemplateFile($filename, $templateConfig);
        if (!$templateFile) {
            $this->throwError(sprintf('Template file %s not found.', $templateConfig['templateDirPath'] . $filename));
        }

        $variables = $this->array_merge_recursive_distinct($generalConfig, $fileVariables);
        $contents = $this->renderFileContents($templateFile, $variables);

//        $destinationFile = $this->getDestinationFile($filename, $containerConfig, $destinationDir);
        $destinationFile = __DIR__ . DIRECTORY_SEPARATOR . $this->getComposeFileName();;
        $this->verbose(sprintf("\tWriting '%s'...", $destinationFile), 2);
        $this->writeFile($destinationFile, $contents);

        if ($variables['executable'] ?? false) {
            $this->verbose(sprintf("\tUpdating permissions on '%s' to '%o'...",
                $destinationFile, $this->executablePermissions), 2);
            $this->setFilePermissions($destinationFile, $this->executablePermissions);
        }



        if ($generalConfig['DOCKER_SERVICES']['venia']) {
            $this->showWarning(
                "P.S. Currently for Venia we just installed Venia sample data on install db phase. \n" .
                "You should setup Venia separately after setup Magento"
            );
        }
    }

//#==============================================================================
//# BLOCK: Common Build logic
//#==============================================================================

    /**
     * Universal container builder
     */
    protected function buildContainer($containerName, $containerConfig)
    {
        $requiredKeys = ['templateDir', 'destinationDir'];
        $isValid = is_array($containerConfig) &&
            !array_diff($requiredKeys, array_keys($containerConfig)) &&
            !in_array('', array_intersect_key($containerConfig, array_flip($requiredKeys)));
        if (!$isValid) {
            $this->throwError(sprintf("Container %s doesn't have required params: %s",
                $containerName, implode(',', $requiredKeys)));
        }

        $this->verbose(sprintf("Building '%s'...", $containerName), 1);
        $configFiles = $containerConfig['files'];
        unset($containerConfig['files']);

        $templateConfig = $this->getTemplateConfig($containerConfig);
        $defaultFileVariables = [
            '_enable_variables' => false,
            'executable' => false
        ];
        foreach ($configFiles as $filename => $fileVariables) {
            $fileVariables = $this->array_merge_recursive_distinct($defaultFileVariables, $fileVariables);
            $this->buildContainerFile($filename, $fileVariables, $containerConfig, $templateConfig);
        }
    }

    /**
     * Build single container file
     */
    protected function buildContainerFile($filename, $fileVariables, $containerConfig, $templateConfig)
    {
        $destinationDir = $containerConfig['destinationDir'];
        $templateFile = $this->getTemplateFile($filename, $templateConfig);

        if (!$templateFile) {
            $this->throwError(sprintf('Template file %s not found.', $templateConfig['templateDirPath'] . $filename));
        }

        $variables = $this->array_merge_recursive_distinct($containerConfig, $fileVariables);
        $contents = $this->renderFileContents($templateFile, $variables);

        $destinationFile = $this->getDestinationFile($filename, $containerConfig, $destinationDir);
        $this->verbose(sprintf("\tWriting '%s'...", $destinationFile), 2);

        $this->writeFile($destinationFile, $contents);

        if ($variables['executable'] ?? false) {
            $this->verbose(sprintf("\tUpdating permissions on '%s' to '%o'...",
                $destinationFile, $this->executablePermissions), 2);
            $this->setFilePermissions($destinationFile, $this->executablePermissions);
        }
    }

    /**
     * Render file contents based on variables
     */
    protected function renderFileContents($templateFile, $fileVariables)
    {
        if ($fileVariables['_enable_variables'] ?? false) {
            $contents = $this->renderTemplate($templateFile, $fileVariables);
        } else {
            $contents = file_get_contents($templateFile);
        }

        $contents = str_replace('{{generated_by_builder}}',
            'This file is automatically generated. Do not edit directly.', $contents);

        return $contents;
    }

    /**
     * Return the first found template file name for the given file.
     * Example for 'Dockerfile':
     * [
     *      'Dockerfile-7.4-cli'
     *      'Dockerfile-7.4'
     *      'Dockerfile-cli' - found
     *      'Dockerfile'
     * ]
     *
     * @param string $filename
     * @param array  $config
     * @return string|null
     */
    protected function getTemplateFile($filename, $config)
    {
        $templateDirPath = $config['templateDirPath'];
        $potentialFilenames = [
            sprintf("%s-%s-%s", $filename, $config['version'], $config['flavour']),
            sprintf("%s-%s", $filename, $config['version']),
            sprintf("%s-%s", $filename, $config['flavour']),
            $filename,
        ];

        if ($config['templateSuffix']) {
            $potentialFilenames = [
                sprintf("%s-%s-%s-%s", $filename, $config['version'], $config['templateSuffix'], $config['flavour']),
                sprintf("%s-%s-%s", $filename, $config['version'], $config['templateSuffix']),
                sprintf("%s-%s-%s", $filename, $config['templateSuffix'], $config['flavour']),
                sprintf("%s-%s", $filename, $config['templateSuffix']),
                ...$potentialFilenames
            ];
        }

        foreach ($potentialFilenames as $potentialFilename) {
            $path = $templateDirPath . $potentialFilename;
            if (file_exists($path)) {
                if (!is_readable($path)) {
                    $this->throwError(sprintf('Template file %s not readable.', $path));
                }
                return $path;
            }
        }

        return null;
    }

    /**
     * Get the destination for the given file.
     *
     * @param string $filename
     * @param array  $config
     * @return string
     */
    protected function getDestinationFile($filename, $config, $destinationFolder)
    {
        return implode(DIRECTORY_SEPARATOR, [
            $destinationFolder,
            $config['context-folder'] ?? $config['version'] . '-' . $config['flavour'],
            $filename,
        ]);
    }

    /**
     * Render the given template file using the provided variables and return the resulting output.
     *
     * @param string $templateFile
     * @param array  $variables
     * @return string
     */
    protected function renderTemplate($templateFile, $variables)
    {
        extract($variables, EXTR_OVERWRITE);
        ob_start();
        include $templateFile;
        $output = ob_get_clean();
        return $output ?: "";
    }

    /**
     * Write the contents to the given file.
     *
     * @param string $filename
     * @param string $contents
     * @return $this
     * @throws Exception
     */
    protected function writeFile($filename, $contents)
    {
        $directory = dirname($filename);

        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                $this->throwError(sprintf("Unable to create directory %s!", $directory));
            }
        }

        if (file_put_contents($filename, $contents) === false) {
            $this->throwError(sprintf("Failed to write %s!", $filename));
        }

        return $this;
    }

    /**
     * Update the permissions on the given file.
     *
     * @param string $filename
     * @param int    $permissions
     * @return $this
     */
    protected function setFilePermissions($filename, $permissions = 0644)
    {
        chmod($filename, $permissions);
        return $this;
    }

//#==============================================================================
//# BLOCK: Get/Prepare Configuration
//#==============================================================================

    /**
     * Get general configuration with defaults
     */
    protected function getGeneralConfig($variables = [])
    {
        if ($this->generalConfig === null) {
            $this->generalConfig = $this->buildGeneralConfig($variables);
        }

        return $this->generalConfig;
    }

    /**
     * @TODO: remove $variables = [] param
     * Build general configuration
     */
    protected function buildGeneralConfig($variables = [])
    {
        $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
        $defaultConfig = $this->getDefaultGeneralConfig();

        $variables = $this->array_merge_recursive_distinct($defaultConfig, $generalConfig, $variables);

        // Apply configuration transformations
        $variables = $this->transformGeneralConfig($variables);

        $phpContainersConfig = $this->getActivePhpContainersConfig2(
            $this->config['php-containers'],
            $variables
        );
        $this->config['php-containers'] = $this->transformPhpContainersConfig(
            $phpContainersConfig,
            $variables
        );

        return $variables;
    }

    /**
     * Get default general configuration
     */
    protected function getDefaultGeneralConfig()
    {
        return [
            'M2_INSTALL' => [],
            'M2_SETTINGS' => [],
            'DOCKER_SERVICES' => [
                'database' => [],
                'search_engine' => false,
                '__note_varnish__' => 'available varnish: true|false',
                'varnish' => false,
                'cron' => false,
                'redis' => false,
                'rabbitmq' => false,
                '__note_mcs__' => 'available magento-coding-standard: true|false',
                'magento-coding-standard' => false,
                '__note_venia__' => 'available venia: true|false',
                'venia' => false
            ],
        ];
        /** just reference for elasticsearch configuration */
//        'search_engine' => [
//            '__note_connect_type__' => 'available connect_type: external|internal|none',
//            'CONNECT_TYPE' => 'internal',
//            '__note_type__' => 'available type: elasticsearch|opensearch',
//            'TYPE' => 'elasticsearch',
//            'VERSION' => '7.7.1'
//        ]
    }

    /**
     * Modify general configuration
     */
    protected function transformGeneralConfig($variables)
    {
        // Handle search engine configuration
        if (is_array($variables['DOCKER_SERVICES']['search_engine'])
            && $variables['DOCKER_SERVICES']['search_engine']['CONNECT_TYPE'] == 'none') {
            $variables['DOCKER_SERVICES']['search_engine'] = false;
        }

        // Set search engine availability
        $variables['M2_SETTINGS']['SEARCH_ENGINE_AVAILABLE'] =
            $variables['DOCKER_SERVICES']['search_engine'] !== false
            && in_array($variables['DOCKER_SERVICES']['search_engine']['CONNECT_TYPE'], ['internal', 'external']);

        /** @deprecated: remove in feature releases */
//        if ($variables['NGINX_PROXY_PATH']) {
//            $variables['NGINX_PROXY_PATH'] = rtrim($variables['NGINX_PROXY_PATH'], '/');
//        }

        if ($generalConfig['DOCKER_SERVICES']['venia'] ?? false) {
            if ($variables['M2_INSTALL']['USE_SAMPLE_DATA'] &&
                $variables['M2_INSTALL']['USE_SAMPLE_DATA'] !== 'false') {
                $variables['M2_INSTALL']['USE_SAMPLE_DATA'] = 'venia';
            }
        }

        return $variables;
    }

    /**
     * Get active PHP containers configuration (фільтрація)
     * @param array $allPhpContainersConfig
     * @param array $generalConfig
     * @return array
     */
    private function getActivePhpContainersConfig2(array $allPhpContainersConfig, array $generalConfig): array
    {
        $buildPhpVersion = $generalConfig['PHP_VERSION'];
        $needMCSphpContainer = $generalConfig['DOCKER_SERVICES']['magento-coding-standard'] ?? false;

        return array_filter(
            $allPhpContainersConfig,
            function ($containerConfig, $name) use ($buildPhpVersion, $needMCSphpContainer) {
                return ($containerConfig['version'] ?? '') === $buildPhpVersion
                    && (!str_contains($name, 'mcs') || $needMCSphpContainer);
            },
            ARRAY_FILTER_USE_BOTH
        );
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
     * Get template configuration
     */
    protected function getTemplateConfig($containerConfig)
    {
        return [
            'templateDirPath' => $containerConfig['templateDir'],
            'version' => $containerConfig['version'] ?? '',
            'flavour' => $containerConfig['flavour'] ?? '',
            'templateSuffix' => $containerConfig['templateSuffix'] ?? '',
        ];
    }
}

/**
 * __MAIN__
 */
$args = getopt("hvq", ["dry-run"]);
$options = [];

if (isset($args["h"])) {
    echo <<<USAGE
Usage: php builder.php [options]

Options:
    -h  Print out this help message.
    -v  Enable verbose output. Can be used multiple times to increase verbosity level.
    --dry-run Will create containers inside 'containers-dry-run' folder & 'compose-dry-run.yaml' (Good for compare prev & new configuration)
    -q  Silence informational messages.
USAGE;
    exit;
}

if (isset($args["q"])) {
    $options["verbose"] = 0;
} else if (isset($args["v"])) {
    $options["verbose"] = is_array($args["v"]) ? count($args["v"]) : 1;
}

if (isset($args["dry-run"])) {
    $options["dry_run"] = true;
}

$builder = new ConfigBuilder($options);
$builder->run();
