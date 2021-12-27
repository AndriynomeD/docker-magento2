<?php

/**
 * Class Builder2
 *
 * Builds files from given configuration and source templates.
 *
 * This extends from the original Builder in the `docker-magento` repository.
 */
class ConfigBuilder
{
    const CONFIG_FILE_NAME = 'config.json';
    const CONFIG_FILE = __DIR__ . DIRECTORY_SEPARATOR . self::CONFIG_FILE_NAME;
    const TEMPLATE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'src';
    const GENERAL_CONFIG_KEY = 'general-config';

    /* Php containers consts */
    const PHP_CONTAINERS_CONFIG_KEY = 'php-containers';
    const PHP_CONTAINERS_TEMPLATE_DIR = 'phpContainers';
    const PHP_CONTAINERS_DESTINATION_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'containers' . DIRECTORY_SEPARATOR . 'php';
    const DEFAULT_COMPOSERVERSION = '1.10.17';

    /* docker-compose consts */
    const DOCKER_COMPOSE_TEMPLATE_FILE = 'docker-compose.tml';
    const DOCKER_COMPOSE_DESTINATION_FILE = __DIR__ . DIRECTORY_SEPARATOR . 'docker-compose.yml';

    const DEFAULT_EXECUTABLE_PERMISSIONS = 0755;
    const DEFAULT_VERBOSE_LEVEL = 1;

    /**
     * Build targets and their configuration.
     *
     * @var array
     */
    protected $config = [];

    /**
     * File permissions for executable files.
     *
     * @var int
     */
    protected $executablePermissions;

    /**
     * Verbosity level.
     *
     * @var int
     */
    protected $verboseLevel;

    public function __construct($options = [])
    {
        $this->executablePermissions = $options['executable_file_permissions'] ?? static::DEFAULT_EXECUTABLE_PERMISSIONS;
        $this->verboseLevel = $options['verbose'] ?? static::DEFAULT_VERBOSE_LEVEL;

        $this->loadConfig(static::CONFIG_FILE);
    }

    /**
     * Build the files described in the loaded config.
     */
    public function run()
    {
        $this->buildPhpContainers();
        $this->buildNginxContainer();
        $this->buildDockerCompose();
    }

    private function getGeneralConfig($variables = [])
    {
        $generalConfig = $this->config[self::GENERAL_CONFIG_KEY];
        $defaultDockerComposeConfig = [
            'PHP_VERSION' => '7.4',
            'M2_VERSION' => '2.4.*',
            'HTTPS_HOST' => false,
            'NGINX_PROXY_PATH' => '',
            'M2_INSTALL' => [],
            'M2_SETTINGS' => [],
            'DOCKER_DB' => [],
            'DOCKER_SERVICES' => []
        ];
        $defaultDockerDbConfig = [
            '__note__'  => 'available image: mariadb|mysql|percona',
            'IMAGE'   => 'mariadb:10.4',
            'TYPE'    => 'mariadb',
            'VERSION' => '10.4'
        ];
        $defaultAdditionalServicesConfig = [
            'external_elasticsearch'    => true,
            'internal_elasticsearch'    => false,
            'varnish'   => true,
            'cron'  => true,
            'redis' => false,
            'rabbitmq'  => false,
            'magento-coding-standard'  => false,
            'venia'   => false
        ];
        $variables = array_merge($defaultDockerComposeConfig, $generalConfig, $variables);
        $variables['DOCKER_DB'] = array_merge($defaultDockerDbConfig, $variables['DOCKER_DB']);
        $variables['DOCKER_SERVICES'] = array_merge($defaultAdditionalServicesConfig, $variables['DOCKER_SERVICES']);

        if ($variables['DOCKER_SERVICES']['external_elasticsearch'] && $variables['DOCKER_SERVICES']['internal_elasticsearch']) {
            throw new Exception( "\033[1;37m\033[0;31m" . 'External & internal elasticsearch cann\'t be enabled at same time' . "\033[0m");
        }
        $variables['ELASTICSEARCH_AVAILABLE'] = $variables['DOCKER_SERVICES']['external_elasticsearch']
            || $variables['DOCKER_SERVICES']['internal_elasticsearch'];
        if (!$variables['ELASTICSEARCH_AVAILABLE']
            && version_compare(str_replace('*', 9, $variables['M2_VERSION']), '2.4.0', '>=')
        ) {
            throw new Exception( "\033[1;37m\033[0;31m" . 'External or Internal Elasticsearch is required for magento 2.4.0+' . "\033[0m");
        }

        if ($generalConfig['DOCKER_SERVICES']['venia']) {
            if ($variables['DOCKER_SERVICES']['varnish']) {
                throw new Exception( "\033[1;37m\033[0;31m" . 'Venia PWA not need Varnish on Magento backend' . "\033[0m");
            }
//            if (!$variables['NGINX_PROXY_PATH']) {
//                throw new Exception( "\033[1;37m\033[0;31m" . 'Venia PWA required `NGINX_PROXY_PATH`' . "\033[0m");
//            }
            if ($variables['M2_INSTALL']['USE_SAMPLE_DATA']) {
                $variables['M2_INSTALL']['USE_SAMPLE_DATA'] = 'venia';
            }
        }

        if ($variables['HTTPS_HOST'] && !$variables['NGINX_PROXY_PATH']) {
            throw new Exception( "\033[1;37m\033[0;31m" . 'Https required `NGINX_PROXY_PATH`' . "\033[0m");
        }

        if ($variables['NGINX_PROXY_PATH']) {
            $variables['NGINX_PROXY_PATH'] = rtrim($variables['NGINX_PROXY_PATH'], '/');
        }

        if (!isset($variables['M2_INSTALL']['EDITION'])) {
            $variables['M2_INSTALL']['EDITION'] = 'community';
        }
        $availableEdition = ['community', 'enterprise'];
        if (!in_array($variables['M2_INSTALL']['EDITION'], $availableEdition)) {
            throw new Exception( "\033[1;37m\033[0;31m"
                . sprintf('Incorrect Edition: %s. Available: %s', $variables['M2_INSTALL']['EDITION'], implode(', ', $availableEdition))
                . "\033[0m");
        }

        return $variables;
    }

    private function getPhpContainerVariables($generalConfig, $phpContainerConfig, $variables = [])
    {
        $defaultPhpContainerConfig = [
            'composerVersion' => 'latest',
            'databaseType' => $generalConfig['DOCKER_DB']['TYPE'],
            'databaseVersion' => $generalConfig['DOCKER_DB']['VERSION'],
            'specificPackages' => []
        ];
        $defaultSpecificPackages = [
            'gd' => true,
            'imagick' => false,
            'calendar' => false,
            'ioncube' => false,
            'grunt' => false,
            'libsodiumfix' => false
        ];
        $defaultFileVariables = [
            '_disable_variables' => false,
            'executable' => false
        ];
        $variables = array_merge(
            $defaultPhpContainerConfig,
            $phpContainerConfig,
            $defaultFileVariables,
            $variables
        );
        $variables['specificPackages'] = array_merge($defaultSpecificPackages, $variables['specificPackages']);

        if ($variables['composerVersion'] === 'latest') {
            $magento2Version = str_replace('*', 9, $generalConfig['M2_VERSION']);
            if (version_compare($magento2Version, '2.4.2', '>=')) {
                $variables['composerVersion'] = 'latest';
            } else {
                $variables['composerVersion'] = self::DEFAULT_COMPOSERVERSION;
            }
        }

        return $variables;
    }

    /**
     * Build the files described in the loaded config.
     */
    private function buildPhpContainers()
    {
        $templateDirPath = self::TEMPLATE_DIR . DIRECTORY_SEPARATOR
            . self::PHP_CONTAINERS_TEMPLATE_DIR . DIRECTORY_SEPARATOR;
        $generalConfig = $this->getGeneralConfig();
        $buildPhpVersion = $generalConfig['PHP_VERSION'];
        $phpContainersConfig = $this->config[self::PHP_CONTAINERS_CONFIG_KEY];
        foreach ($phpContainersConfig as $name => $phpContainerConfig) {
            if ($phpContainerConfig['version'] != $buildPhpVersion
                || (strpos($name, 'mcs') !== false && !$generalConfig['DOCKER_SERVICES']['magento-coding-standard'])) {
                // delete not used or just ignore them?
                continue;
            }
            $this->verbose(sprintf("Building '%s'...", $name), 1);
            $configFiles = $phpContainerConfig['files'];
            unset($phpContainerConfig['files']);
            $phpContainerConfig['phpVersion'] = $phpContainerConfig['version'];
            $templateConfig = [
                'templateDirPath' => $templateDirPath,
                'version' => $phpContainerConfig['version'],
                'flavour' => $phpContainerConfig['flavour'],
            ];
            foreach ($configFiles as $filename => $variables) {
                $contents = '';
                if ($templateFile = $this->getTemplateFile($filename, $templateConfig)) {
                    /* list of all variables
                    $variables = [
                        'composerVersion', 'databaseType', 'databaseVersion',
                        'phpVersion', 'flavour', 'packages', 'phpExtensions', 'specificPackages', 'xdebugVersion',
                        '_disable_variables', 'executable',
                    ];
                    */

                    $variables = $this->getPhpContainerVariables($generalConfig, $phpContainerConfig, $variables);

                    // Determine whether we should load with the template renderer, or whether we should straight up
                    // just load the file from disk.
                    if ($variables['_disable_variables']) {
                        $contents = file_get_contents($templateFile);
                    } else {
                        $contents = $this->renderTemplate($templateFile, $variables);
                    }

                    $contents = str_replace('{{generated_by_builder}}', 'This file is automatically generated. Do not edit directly.', $contents);
                }

                $destinationFile = $this->getDestinationFile(
                    $filename,
                    $phpContainerConfig,
                    self::PHP_CONTAINERS_DESTINATION_DIR
                );
                $this->verbose(sprintf("\tWriting '%s'...", $destinationFile), 2);
                $this->writeFile($destinationFile, $contents);

                if ($variables['executable'] ) {
                    $this->verbose(sprintf("\tUpdating permissions on '%s' to '%o'...", $destinationFile, $this->executablePermissions), 2);
                    $this->setFilePermissions($destinationFile, $this->executablePermissions);
                }
            }
        }
    }

    /**
     * Currently only generate ssl sertificates
     */
    private function buildNginxContainer()
    {
        $generalConfig = $this->getGeneralConfig();
        $httpsOn = $generalConfig['HTTPS_HOST'];
        if ($httpsOn) {
            $this->verbose(sprintf("Generate ssl sertificates for Magento..."), 1);
            $hosts = $generalConfig['M2_VIRTUAL_HOSTS'];
            $nginxProxyPath = $generalConfig['NGINX_PROXY_PATH'];
            shell_exec(sprintf('bash generateSSL.sh %s %s', $hosts, $nginxProxyPath));
        }
    }

    private function buildDockerCompose()
    {
        $templateDirPath = self::TEMPLATE_DIR . DIRECTORY_SEPARATOR;
        $generalConfig = $this->getGeneralConfig();
        $this->verbose(sprintf("Building '%s'...", 'docker-compose.yml'), 1);
        $templateConfig = [
            'templateDirPath' => $templateDirPath,
            'version' => 'none',
            'flavour' => 'none',
        ];
        $filename = self::DOCKER_COMPOSE_TEMPLATE_FILE;
        $contents = '';
        if ($templateFile = $this->getTemplateFile($filename, $templateConfig)) {
            $variables = $generalConfig;
            $contents = $this->renderTemplate($templateFile, $variables);
            $contents = str_replace('{{generated_by_builder}}', 'This file is automatically generated. Do not edit directly.', $contents);
        }

        if ($generalConfig['DOCKER_SERVICES']['venia']) {
            $this->verbose("\033[1;37m\033[1;33m" . "P.S. Currently for Venia we just installed Venia sample data on install db phase. \n"
                . "You should setup Venia separately after setup Magento" . "\033[0m", 1);
        }

        $destinationFile = self::DOCKER_COMPOSE_DESTINATION_FILE;
        $this->verbose(sprintf("\tWriting '%s'...", $destinationFile), 2);
        $this->writeFile($destinationFile, $contents);
        $this->setFilePermissions($destinationFile, $this->executablePermissions);
    }

    /**
     * Load the build configuration from the given file.
     *
     * @param string $file
     *
     * @return $this
     * @throws Exception
     */
    protected function loadConfig($file)
    {
        if (!(file_exists($file) && is_readable($file))) {
            throw new Exception(sprintf("File %s not exist or not readable!", self::CONFIG_FILE_NAME));
        }

        $config = json_decode(file_get_contents($file), true);

        if (!is_array($config)
            || !array_key_exists(static::GENERAL_CONFIG_KEY, $config)
            || (!array_key_exists(static::PHP_CONTAINERS_CONFIG_KEY, $config)
                || !is_array($config[static::PHP_CONTAINERS_CONFIG_KEY]))
        ) {
            throw new Exception(sprintf("Invalid configuration in %s!", $file));
        }

        $this->config = $config;

        return $this;
    }

    /**
     * Return the template file name for the given file.
     * Example:
     * Dockerfile-7.4-cli
     * Dockerfile-7.4
     * Dockerfile-cli - found
     * Dockerfile
     *
     * @param string $filename
     * @param array  $config
     *
     * @return null|string
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
        foreach ($potentialFilenames as $potentialFilename) {
            $path =  $templateDirPath . $potentialFilename;
            if (file_exists($path) && is_readable($path)) {
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
     *
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
     *
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
     *
     * @return $this
     * @throws Exception
     */
    protected function writeFile($filename, $contents)
    {
        $directory = dirname($filename);

        // If the directory doesn't created then try to create the directory.
        if (!is_dir($directory)) {
            // Create the directory, preventing race conditions if another process creates the directory for us.
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new Exception(sprintf("Unable to create directory %s!", $directory));
            }
        }

        if (file_put_contents($filename, $contents) === false) {
            throw new Exception(sprintf("Failed to write %s!", $filename));
        }

        return $this;
    }

    /**
     * Update the permissions on the given file.
     *
     * @param string $filename
     * @param int    $permissions
     *
     * @return $this
     */
    protected function setFilePermissions($filename, $permissions = 0644)
    {
        chmod($filename, $permissions);

        return $this;
    }

    /**
     * Print an informational message to the command line.
     *
     * @param string $message
     * @param int    $level
     * @param bool   $newline
     *
     * @return $this
     */
    protected function verbose($message, $level = 1, $newline = true)
    {
        if ($level <= $this->verboseLevel) {
            printf("%s%s", $message, $newline ? PHP_EOL : "");
        }

        return $this;
    }
}

/**
 * __MAIN__
 */

$args = getopt("hvq");
$options = [];

if (isset($args["h"])) {
    echo <<<USAGE
Usage: php builder.php [options]

Options:
    -h  Print out this help message.
    -v  Enable verbose output. Can be used multiple times to increase verbosity level.
    -q  Silence informational messages.
USAGE;
    exit;
}

if (isset($args["q"])) {
    $options["verbose"] = 0;
} else if (isset($args["v"])) {
    $options["verbose"] = count($args["v"]);
}

$builder = new ConfigBuilder($options);
$builder->run();
