<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Builder;

use DockerBuilder\Core\Contract\ConfigGeneratorInterface;
use DockerBuilder\Core\Contract\ConfigLoaderInterface;
use DockerBuilder\Core\Contract\ConfigValidatorInterface;
use DockerBuilder\Core\Contract\FileManagerInterface;
use DockerBuilder\Core\Contract\LoggerInterface;
use DockerBuilder\Core\Contract\TemplateRendererInterface;
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

    const CONFIG_FILE_NAME = 'config.json';
    const CONFIG_FILE = self::ROOT_DIR . self::CONFIG_FILE_NAME;
    const TEMPLATE_DIR = self::APP_DIR . 'resources/templates/';
    const CONTAINERS_BASE_DIR = self::ROOT_DIR . 'containers';
    const CONTAINERS_DRY_RUN_DIR = self::ROOT_DIR . 'containers-dry-run';

    const DEFAULT_EXECUTABLE_PERMISSIONS = 0755;

    private ConfigLoaderInterface $configLoader;
    private ConfigValidatorInterface $configValidator;
    private ConfigGeneratorInterface $configGenerator;
    private TemplateRendererInterface $templateRenderer;
    private FileManagerInterface $fileManager;
    private LoggerInterface $logger;

    private array $config = [];
    private int $executablePermissions;
    private bool $dryRun;

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

        $this->dryRun = $options['dry_run'] ?? false;
        if ($this->dryRun) {
            $this->logger->info('DRY RUN MODE: Files will be created in separate directories for comparison', MyOutput::VERBOSITY_NORMAL);
        }

        $this->loadAndProcessConfig();
    }

    /**
     * Load and process configuration through the pipeline
     * @throws Exception
     */
    private function loadAndProcessConfig(): void
    {
        try {
            $rawConfig = $this->configLoader->loadConfig(self::CONFIG_FILE);
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
        $this->buildContainers();

        if ($this->dryRun) {
            $this->logger->success('DRY RUN COMPLETED', MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('Check the following for comparison:', MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('- Containers: ' . $this->getContainersBaseDirPath(), MyOutput::VERBOSITY_NORMAL);
            $this->logger->info('- Compose file: ' . $this->getComposeFileName(), MyOutput::VERBOSITY_NORMAL);
        }
    }

    /**
     * Build all containers and compose.yaml file
     */
    protected function buildContainers(): void
    {
        $this->buildPhpContainers();
        $this->buildNginxContainer();
        $this->buildSearchEngineContainer();
        $this->buildDockerCompose();
    }

    /**
     * Build PHP containers (множинний - містить цикл)
     */
    protected function buildPhpContainers(): void
    {
        $phpContainersConfig = $this->config['php-containers'];

        foreach ($phpContainersConfig as $name => $containerConfig) {
            // Підготовка параметрів для PHP контейнера
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
        $generalConfig = $this->config['general-config'];

        // Поки що тільки SSL сертифікати, але можна розширити
        // SSL generation logic here if needed

        $this->logger->success("Nginx container configuration completed", MyOutput::VERBOSITY_VERBOSE);
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
        $templateConfig = [
            'templateDirPath' => self::TEMPLATE_DIR,
            'version' => '',
            'flavour' => '',
            'templateSuffix' => ''
        ];

        $filename = 'compose-template.php';
        $fileVariables = ['_enable_variables' => true, 'executable' => true];

        try {
            $templateFile = $this->templateRenderer->findTemplate($filename, $templateConfig);
            if (!$templateFile) {
                $this->logger->error(sprintf('Template file %s not found.', $templateConfig['templateDirPath'] . $filename));
            }

            $variables = ArrayUtil::arrayMergeRecursiveDistinct($generalConfig, $fileVariables);
            $content = $this->templateRenderer->render($templateFile, $variables);

            $destinationFile = self::ROOT_DIR . $this->getComposeFileName();
            $this->logger->infoLight(sprintf("\tWriting '%s'...", $destinationFile), MyOutput::VERBOSITY_VERBOSE);
            $this->fileManager->writeFile($destinationFile, $content);

            if ($variables['executable'] ?? false) {
                $this->logger->infoLight(sprintf("\tUpdating permissions on '%s' to '%o'...",
                    $destinationFile, $this->executablePermissions), MyOutput::VERBOSITY_VERBOSE);
                $this->fileManager->setPermissions($destinationFile, $this->executablePermissions);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception($e->getMessage());
        }

        if ($generalConfig['DOCKER_SERVICES']['venia'] ?? false) {
            $this->logger->warning(
                "P.S. Currently for Venia we just installed Venia sample data on install db phase. \n" .
                "You should setup Venia separately after setup Magento"
            );
        }
    }

    /**
     * Universal container builder (уніфікована логіка)
     * @param string $containerName - використовується для додавання до destinationDir
     * @param array $containerConfig
     */
    protected function buildContainer(string $containerName, array $containerConfig): void
    {
        $requiredKeys = ['templateDir', 'destinationDir'];
        $isValid = is_array($containerConfig) &&
            !array_diff($requiredKeys, array_keys($containerConfig)) &&
            !in_array('', array_intersect_key($containerConfig, array_flip($requiredKeys)));

        if (!$isValid) {
            $this->logger->error(sprintf("Container %s doesn't have required params: %s",
                $containerName, implode(',', $requiredKeys)));
        }

        $this->logger->infoLight(sprintf("Building container '%s'...", $containerName), MyOutput::VERBOSITY_NORMAL);
        $containerConfig['destinationDir'] .= DIRECTORY_SEPARATOR . $containerName;
        $configFiles = $containerConfig['files'] ?? [];
        unset($containerConfig['files']);

        $templateConfig = [
            'templateDirPath' => $containerConfig['templateDir'],
            'version' => $containerConfig['version'] ?? '',
            'flavour' => $containerConfig['flavour'] ?? '',
            'templateSuffix' => $containerConfig['templateSuffix'] ?? ''
        ];

        foreach ($configFiles as $filename => $fileConfig) {
            $this->buildContainerFile($filename, $fileConfig, $containerConfig, $templateConfig);
        }
    }

    /**
     * Build single container file (уніфікована логіка для файлів)
     * @param string $filename
     * @param array $fileConfig
     * @param array $containerConfig
     * @param array $templateConfig
     */
    protected function buildContainerFile(
        string $filename,
        array $fileConfig,
        array $containerConfig,
        array $templateConfig
    ): void {
        try {
            $destinationDir = $containerConfig['destinationDir'];
            $templateFile = $this->templateRenderer->findTemplate($filename, $templateConfig);

            if (!$templateFile) {
                $this->logger->error(sprintf('Template file %s not found in %s', $filename, $templateConfig['templateDirPath']));
            }

            $fileVariables = [
                '_enable_variables' => $fileConfig['_enable_variables'] ?? false,
                'executable' => $fileConfig['executable'] ?? false
            ];
            $variables = ArrayUtil::arrayMergeRecursiveDistinct($containerConfig, $fileVariables);
            $content = $this->templateRenderer->render($templateFile, $variables);

            $destinationFile = $destinationDir . DIRECTORY_SEPARATOR . $filename;
            $this->logger->infoLight(sprintf("\tWriting '%s'...", $destinationFile), MyOutput::VERBOSITY_VERBOSE);
            $this->fileManager->writeFile($destinationFile, $content);

            if ($fileConfig['executable'] ?? false) {
                $this->logger->infoLight(sprintf("\tSetting executable permissions on '%s'...", $destinationFile), MyOutput::VERBOSITY_VERBOSE);
                $this->fileManager->setPermissions($destinationFile, $this->executablePermissions);
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            throw new Exception($e->getMessage());
        }
    }

    protected function getContainersDirPath($containersDir): string
    {
        return $this->getContainersBaseDirPath() . DIRECTORY_SEPARATOR . $containersDir;
    }

    // Helper методи
    protected function getContainersBaseDirPath(): string
    {
        return $this->dryRun ? self::CONTAINERS_DRY_RUN_DIR : self::CONTAINERS_BASE_DIR;
    }

    protected function getComposeFileName(): string
    {
        return $this->dryRun ? 'compose-dry-run.yaml' : 'compose.yaml';
    }
}
