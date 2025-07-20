<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Builder;

use DockerBuilder\Core\Config\ConfigGeneratorInterface;
use DockerBuilder\Core\Config\ConfigLoaderInterface;
use DockerBuilder\Core\Config\ConfigValidatorInterface;
use DockerBuilder\Core\File\FileManagerInterface;
use DockerBuilder\Core\Logger\LoggerInterface;
use DockerBuilder\Core\Template\TemplateRendererInterface;
use Exception;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ConfigBuilder
 *
 * Orchestrates the configuration building process using separate components
 */
class ConfigBuilder
{
    private const APP_DIR = __DIR__ . '/../../'; /* docker-builder folder */
    private const ROOT_DIR = self::APP_DIR . '../';  /* 1 level up from the docker-builder folder */

    private const CONFIG_FILEPATH = self::ROOT_DIR . 'config.json';
    private const TEMPLATE_DIR = self::APP_DIR . 'resources' . DIRECTORY_SEPARATOR . 'templates';

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
        $this->templateRenderer->setTemplatesPath(self::TEMPLATE_DIR);
    }

    /**
     * Load and process configuration through the pipeline
     * @throws Exception
     */
    private function loadAndProcessConfig(): void
    {
        try {
            $rawConfig = $this->configLoader->loadConfig($this->getRealPath(self::CONFIG_FILEPATH));
            $this->configValidator->validate($rawConfig);
            $this->config = $this->configGenerator->generate($rawConfig);
            $this->logger->success('Configuration loaded and processed successfully', OutputInterface::VERBOSITY_VERBOSE);
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
            $this->logger->infoBolt('DRY RUN MODE: Files will be created in separate directories for comparison', OutputInterface::VERBOSITY_NORMAL);
        }
        $this->loadAndProcessConfig();
        $this->build();

        if ($this->isDryRun) {
            $this->logger->success('DRY RUN COMPLETED', OutputInterface::VERBOSITY_NORMAL);
            $this->logger->infoBolt('Check the following for comparison:', OutputInterface::VERBOSITY_NORMAL);
            $this->logger->infoBolt('- Env Files: ' . $this->getEnvFilesDirPath(), OutputInterface::VERBOSITY_NORMAL);
            $this->logger->infoBolt('- Containers: ' . $this->getContainersBaseDirPath(), OutputInterface::VERBOSITY_NORMAL);
            $this->logger->infoBolt('- Compose file: ' . $this->getComposeFileName(), OutputInterface::VERBOSITY_NORMAL);
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
        $this->logger->info("Building env files...", OutputInterface::VERBOSITY_NORMAL);

        $generalConfig = $this->config['general-config'];
        $envFilesConfig = [
            'files' => [
                'm2_install.env' => ['_enable_variables' => true]
            ]
        ];

        /** Prepare data for buildFile */
        $configFiles = $envFilesConfig['files'] ?? [];
        $commonVariables = $generalConfig;
        foreach ($configFiles as $filename => $fileConfig) {
            $fileConfig['templateSubDir'] = 'envs' . DIRECTORY_SEPARATOR;
            $fileConfig['destinationDir'] = $this->getEnvFilesDirPath();
            $this->buildFile($filename, $fileConfig, $commonVariables);
        }
    }

    /**
     * Build PHP containers
     */
    protected function buildPhpContainers(): void
    {
        $phpContainersConfig = $this->config['php-containers'];
        foreach ($phpContainersConfig as $name => $containerConfig) {
            $containerConfig['templateSubDir'] = 'phpContainers' . DIRECTORY_SEPARATOR;
            $containerConfig['destinationDir'] = $this->getContainersDirPath('php') . DIRECTORY_SEPARATOR . $name;

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
                'templateSubDir' => 'search_engine' . DIRECTORY_SEPARATOR . $searchEngineType . DIRECTORY_SEPARATOR,
                'destinationDir' => $this->getContainersDirPath('search_engine') . DIRECTORY_SEPARATOR . $searchEngineType,
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
        $this->logger->info(sprintf("Building '%s'...", $this->getComposeFileName()), OutputInterface::VERBOSITY_NORMAL);

        $generalConfig = $this->config['general-config'];
        $composeFileConfig = [
            'files' => [
                $this->getComposeFileName() => [
                    '_enable_variables' => true,
                    'template_name' => 'compose.yaml.twig'
                ],
            ]
        ];

        /** Prepare data for buildFile */
        $configFiles = $composeFileConfig['files'] ?? [];
        $commonVariables = $generalConfig;
        foreach ($configFiles as $filename => $fileConfig) {
            $fileConfig['templateSubDir'] = '';
            $fileConfig['destinationDir'] = $this->getRealPath(self::ROOT_DIR);
            $this->buildFile($filename, $fileConfig, $commonVariables);
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
        $requiredKeys = ['templateSubDir', 'destinationDir'];
        $isValid = is_array($containerConfig) &&
            !array_diff($requiredKeys, array_keys($containerConfig));
        if (!$isValid) {
            throw new Exception(sprintf("Container %s doesn't have required params: %s",
                $containerName, implode(', ', $requiredKeys)));
        }

        $this->logger->info(sprintf("Building container '%s'...", $containerName), OutputInterface::VERBOSITY_NORMAL);

        /** Prepare data for buildContainerFile */
        $configFiles = $containerConfig['files'] ?? [];
        $commonVariables = $containerConfig;
        unset($commonVariables['files'], $commonVariables['templateSubDir'], $commonVariables['destinationDir']);
        foreach ($configFiles as $filename => $fileConfig) {
            $fileConfig['templateSubDir'] = $containerConfig['templateSubDir'];
            $fileConfig['destinationDir'] = $containerConfig['destinationDir'];
            $this->buildContainerFile($filename, $fileConfig, $commonVariables);
        }
    }

    /**
     * @param string $filename
     * @param array $fileConfig
     * @param array $commonVariables
     */
    protected function buildContainerFile(
        string $filename,
        array  $fileConfig,
        array  $commonVariables
    ): void {
        $this->buildFile($filename, $fileConfig, $commonVariables);
    }

    /**
     * @param string $filename
     * @param array{
     *      templateSubDir: string,
     *      destinationDir: string,
     *      template_name?: string,
     *      _enable_variables?: bool,
     *      executable?: bool,
     *  } $fileConfig
     * @param array $commonVariables
     */
    protected function buildFile(
        string $filename,
        array  $fileConfig,
        array  $commonVariables
    ): void {
        $requiredKeys = ['templateSubDir', 'destinationDir'];
        $isValid = is_array($fileConfig) &&
            !array_diff($requiredKeys, array_keys($fileConfig));
        if (!$isValid) {
            throw new Exception(sprintf("File %s Config doesn't have required params: %s",
                $filename, implode(', ', $requiredKeys)));
        }

        // set default variables
        $fileConfig['_enable_variables'] = $fileConfig['_enable_variables'] ?? false;
        $fileConfig['executable'] = $fileConfig['executable'] ?? false;

        $templateFile = $this->templateRenderer->findTemplate($filename, $fileConfig);
        if (!$templateFile) {
            throw new Exception(sprintf('Template file %s not found in %s', $filename, $fileConfig['templateSubDir']));
        }

        $content = $this->templateRenderer->render($templateFile, $fileConfig, $commonVariables);
        $destinationFile = $fileConfig['destinationDir'] . DIRECTORY_SEPARATOR . $filename;
        $this->logger->info(sprintf("\tWriting '%s'...", $destinationFile), OutputInterface::VERBOSITY_VERBOSE);
        $this->fileManager->writeFile($destinationFile, $content);

        if ($fileConfig['executable'] ?? false) {
            $this->logger->info(sprintf("\tSetting executable permissions on '%s'...", $destinationFile), OutputInterface::VERBOSITY_VERBOSE);
            $this->fileManager->setPermissions($destinationFile, $this->executablePermissions);
        }
    }

    protected function getRealPath(string $path): string
    {
        return realpath($path);
    }

    protected function getContainersDirPath($containersDir): string
    {
        return $this->getContainersBaseDirPath() . DIRECTORY_SEPARATOR . $containersDir;
    }

    protected function getContainersBaseDirPath(): string
    {
        return $this->isDryRun
            ? $this->getRealPath(self::ROOT_DIR) . 'containers-dry-run'
            : $this->getRealPath(self::ROOT_DIR) . 'containers';
    }

    protected function getEnvFilesDirPath(): string
    {
        return $this->isDryRun
            ? $this->getRealPath(self::ROOT_DIR) . 'envs-dry-run'
            : $this->getRealPath(self::ROOT_DIR) . 'envs';
    }

    protected function getComposeFileName(): string
    {
        return $this->isDryRun
            ? 'compose-dry-run.yaml'
            : 'compose.yaml';
    }
}
