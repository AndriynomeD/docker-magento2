<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Builder;

use DockerBuilder\Core\Config\ConfigGeneratorInterface;
use DockerBuilder\Core\Config\ConfigLoaderInterface;
use DockerBuilder\Core\Config\ConfigValidatorInterface;
use DockerBuilder\Core\File\FileManagerInterface;
use DockerBuilder\Core\Logger\LoggerInterface;
use DockerBuilder\Core\Template\TemplateRendererInterface;
use DockerBuilder\Core\Util\ArrayUtil;
use Exception;

/**
 * Class ConfigBuilder
 *
 * Orchestrates the configuration building process using separate components
 */
class ConfigBuilder
{
    private const APP_DIR = __DIR__ . '/../../'; /* docker-builder folder */
    private const ROOT_DIR = self::APP_DIR . '../';  /* 1 level up from the docker-builder folder */

    const CONFIG_FILEPATH = self::ROOT_DIR . 'config.json';
    const TEMPLATE_DIR = self::APP_DIR . 'resources/templates/';

    const DEFAULT_EXECUTABLE_PERMISSIONS = 0755;

    private ConfigLoaderInterface $configLoader;
    private ConfigValidatorInterface $configValidator;
    private ConfigGeneratorInterface $configGenerator;
    private TemplateRendererInterface $templateRenderer;
    private FileManagerInterface $fileManager;
    private LoggerInterface $logger;

    private array $config = [];
    private int $executablePermissions;
    private bool $isDryRun;

    /**
     * @param ConfigLoaderInterface $configLoader
     * @param ConfigValidatorInterface $configValidator
     * @param ConfigGeneratorInterface $configGenerator
     * @param TemplateRendererInterface $templateRenderer
     * @param FileManagerInterface $fileManager
     * @param LoggerInterface $logger
     * @param array $options
     * @throws Exception
     */
    public function __construct(
        ConfigLoaderInterface $configLoader,
        ConfigValidatorInterface $configValidator,
        ConfigGeneratorInterface $configGenerator,
        TemplateRendererInterface $templateRenderer,
        FileManagerInterface $fileManager,
        LoggerInterface $logger,
        array $options = []
    ) {
        $this->configLoader = $configLoader;
        $this->configValidator = $configValidator ;
        $this->configGenerator = $configGenerator ;
        $this->templateRenderer = $templateRenderer;
        $this->fileManager = $fileManager;
        $this->logger = $logger;

        $this->executablePermissions = $options['executable_file_permissions'] ?? self::DEFAULT_EXECUTABLE_PERMISSIONS;
        if (isset($options['verbose'])) {
            $this->logger->setVerbosity($options['verbose']);
        }
        $this->isDryRun = $options['dry_run'] ?? false;
    }

    /**
     * Load and process configuration through the pipeline
     * @throws Exception
     */
    private function loadAndProcessConfig(): void
    {
        try {
            $rawConfig = $this->configLoader->loadConfig(self::CONFIG_FILEPATH);
            $this->configValidator->validate($rawConfig);
            $this->config = $this->configGenerator->generate($rawConfig);
            $this->logger->success('Configuration loaded and processed successfully', MyOutput::VERBOSITY_VERBOSE);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Build the docker infrastructure based on the processed config
     */
    public function run(): void
    {
        if ($this->isDryRun) {
            $this->logger->info('DRY RUN MODE: Files will be created in separate directories for comparison', MyOutput::VERBOSITY_NORMAL);
        }
        $this->loadAndProcessConfig();
        $this->build();

        if ($this->isDryRun) {
            $this->logger->success('DRY RUN COMPLETED', MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('Check the following for comparison:', MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('- Env Files: ' . $this->getEnvFilesDirPath(), MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('- Containers: ' . $this->getContainersBaseDirPath(), MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('- Compose file: ' . $this->getComposeFileName(), MyOutput::VERBOSITY_NORMAL);
        }
    }

    /**
     * Build infrastructure
     */
    protected function build(): void
    {
        try {
            $this->buildEnvFiles();
            $this->buildPhpContainers();
            $this->buildNginxContainer();
            $this->buildSearchEngineContainer();
            $this->buildDockerCompose();
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    protected function buildEnvFiles(): void
    {
        $this->logger->infoLight("Building env files...", MyOutput::VERBOSITY_NORMAL);

        $generalConfig = $this->config['general-config'];
        $envFilesConfig = [
            'files' => [
                'm2_install.env' => [
                    '_enable_variables' => true,
                    'template_name' => 'm2_install.env.tml'
                ],
            ]
        ];

        /** Prepare data for buildFile */
        $configFiles = $envFilesConfig['files'] ?? [];
        $commonVariables = $generalConfig;
        $templateConfig = [
            'templateDirPath' => self::TEMPLATE_DIR . 'envs' . DIRECTORY_SEPARATOR,
            'destinationDir' => $this->getEnvFilesDirPath()
        ];

        foreach ($configFiles as $filename => $fileConfig) {
            $templateConfig['template_name'] = $fileConfig['template_name'] ?? '';
            $this->buildFile($filename, $fileConfig, $commonVariables, $templateConfig);
        }
    }

    /**
     * Build PHP containers
     */
    protected function buildPhpContainers(): void
    {
        $phpContainersConfig = $this->config['php-containers'];

        foreach ($phpContainersConfig as $name => $containerConfig) {
            $containerConfig['templateDir'] = self::TEMPLATE_DIR . 'phpContainers' . DIRECTORY_SEPARATOR;
            $containerConfig['destinationDir'] = $this->getContainersDirPath('php');

            $this->buildContainer($name, $containerConfig);
        }
    }

    /**
     * Build nginx container (одиничний)
     */
    protected function buildNginxContainer(): void
    {
        /** Now it just placeholder */
    }

    /**
     * Build search engine container
     */
    protected function buildSearchEngineContainer(): void
    {
        $generalConfig = $this->config['general-config'];
        $searchEngineConfig = $generalConfig['DOCKER_SERVICES']['search_engine'] ?? false;

        if ($searchEngineConfig !== false && $searchEngineConfig['CONNECT_TYPE'] === 'internal') {
            $searchEngineType = $searchEngineConfig['TYPE'];
            $searchEngineVersion = $searchEngineConfig['VERSION'];

            $containerConfig = [
                'version' => $searchEngineVersion,
                'templateDir' => self::TEMPLATE_DIR . 'search_engine' . DIRECTORY_SEPARATOR . $searchEngineType . DIRECTORY_SEPARATOR,
                'destinationDir' => $this->getContainersDirPath('search_engine'),
                'files' => [
                    'Dockerfile' => ['_enable_variables' => true]
                ],
                'ELASTICSEARCH_VERSION' => $searchEngineType === 'elasticsearch' ? $searchEngineVersion : '',
                'OPENSEARCH_VERSION' => $searchEngineType === 'opensearch' ? $searchEngineVersion : ''
            ];

            $this->buildContainer($searchEngineType, $containerConfig);
        }
    }

    /**
     * Build Docker compose.yaml file
     */
    protected function buildDockerCompose(): void
    {
        $this->logger->infoLight(sprintf("Building '%s'...", $this->getComposeFileName()), MyOutput::VERBOSITY_NORMAL);

        $generalConfig = $this->config['general-config'];
        $composeFileConfig = [
            'files' => [
                $this->getComposeFileName() => [
                    '_enable_variables' => true,
                    'template_name' => 'compose-template.php'
                ],
            ]
        ];

        /** Prepare data for buildFile */
        $configFiles = $composeFileConfig['files'] ?? [];
        $commonVariables = $generalConfig;
        $templateConfig = [
            'templateDirPath' => self::TEMPLATE_DIR,
            'destinationDir' => self::ROOT_DIR,
        ];

        foreach ($configFiles as $filename => $fileConfig) {
            $templateConfig['template_name'] = $fileConfig['template_name'] ?? '';
            $this->buildFile($filename, $fileConfig, $commonVariables, $templateConfig);
        }

        if ($generalConfig['DOCKER_SERVICES']['venia'] ?? false) {
            $this->logger->warning(
                "P.S. Currently for Venia we just installed Venia sample data on install db phase. \n" .
                "You should setup Venia separately after setup Magento"
            );
        }
    }

    /**
     * @param string $containerName
     * @param array $containerConfig
     */
    protected function buildContainer(string $containerName, array $containerConfig): void
    {
        $requiredKeys = ['templateDir', 'destinationDir'];
        $isValid = is_array($containerConfig) &&
            !array_diff($requiredKeys, array_keys($containerConfig)) &&
            !in_array('', array_intersect_key($containerConfig, array_flip($requiredKeys)));

        if (!$isValid) {
            throw new Exception(sprintf("Container %s doesn't have required params: %s",
                $containerName, implode(', ', $requiredKeys)));
        }

        $this->logger->infoLight(sprintf("Building container '%s'...", $containerName), MyOutput::VERBOSITY_NORMAL);
        $containerConfig['destinationDir'] .= DIRECTORY_SEPARATOR . $containerName;

        /** Prepare data for buildContainerFile */
        $configFiles = $containerConfig['files'] ?? [];
        $commonVariables = $containerConfig;
        unset($commonVariables['files'], $commonVariables['templateDir'], $commonVariables['destinationDir']);
        $templateConfig = [
            'templateDirPath' => $containerConfig['templateDir'],
            'version' => $containerConfig['version'] ?? '',
            'flavour' => $containerConfig['flavour'] ?? '',
            'templateSuffix' => $containerConfig['templateSuffix'] ?? '',
            'destinationDir' => $containerConfig['destinationDir']
        ];

        foreach ($configFiles as $filename => $fileConfig) {
            $templateConfig['template_name'] = $fileConfig['template_name'] ?? '';
            $this->buildContainerFile($filename, $fileConfig, $commonVariables, $templateConfig);
        }
    }

    /**
     * @param string $filename
     * @param array $fileConfig
     * @param array $commonVariables
     * @param array $templateConfig
     */
    protected function buildContainerFile(
        string $filename,
        array  $fileConfig,
        array  $commonVariables,
        array  $templateConfig
    ): void {
        $this->buildFile($filename, $fileConfig, $commonVariables, $templateConfig);
    }

    /**
     * @param string $filename
     * @param array $fileConfig
     * @param array $commonVariables
     * @param array $templateConfig
     */
    protected function buildFile(
        string $filename,
        array  $fileConfig,
        array  $commonVariables,
        array  $templateConfig
    ): void {
        $destinationDir = $templateConfig['destinationDir'];
        $templateFile = $this->templateRenderer->findTemplate($filename, $templateConfig);

        if (!$templateFile) {
            throw new Exception(sprintf('Template file %s not found in %s', $filename, $templateConfig['templateDirPath']));
        }

        $fileVariables = [
            '_enable_variables' => $fileConfig['_enable_variables'] ?? false,
            'executable' => $fileConfig['executable'] ?? false
        ];
        $variables = ArrayUtil::arrayMergeRecursiveDistinct($commonVariables, $fileVariables);
        $content = $this->templateRenderer->render($templateFile, $variables);

        $destinationFile = $destinationDir . DIRECTORY_SEPARATOR . $filename;
        $this->logger->infoLight(sprintf("\tWriting '%s'...", $destinationFile), MyOutput::VERBOSITY_VERBOSE);
        $this->fileManager->writeFile($destinationFile, $content);

        if ($fileConfig['executable'] ?? false) {
            $this->logger->infoLight(sprintf("\tSetting executable permissions on '%s'...", $destinationFile), MyOutput::VERBOSITY_VERBOSE);
            $this->fileManager->setPermissions($destinationFile, $this->executablePermissions);
        }
    }

    protected function getContainersDirPath($containersDir): string
    {
        return $this->getContainersBaseDirPath() . DIRECTORY_SEPARATOR . $containersDir;
    }

    protected function getContainersBaseDirPath(): string
    {
        return $this->isDryRun
            ? self::ROOT_DIR . 'containers-dry-run'
            : self::ROOT_DIR . 'containers';
    }

    protected function getEnvFilesDirPath(): string
    {
        return $this->isDryRun
            ? self::ROOT_DIR . 'envs-dry-run'
            : self::ROOT_DIR . 'envs';
    }

    protected function getComposeFileName(): string
    {
        return $this->isDryRun
            ? 'compose-dry-run.yaml'
            : 'compose.yaml';
    }
}
