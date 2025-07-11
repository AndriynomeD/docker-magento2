<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Builder;

use DockerBuilder\Core\Contract\ConfigGeneratorInterface;
use DockerBuilder\Core\Contract\ConfigLoaderInterface;
use DockerBuilder\Core\Contract\ConfigValidatorInterface;
use DockerBuilder\Core\Contract\FileManagerInterface;
use DockerBuilder\Core\Contract\LoggerInterface;
use DockerBuilder\Core\Contract\TemplateRendererInterface;
use Exception;

class ConfigBuilderFactory
{
    private ConfigLoaderInterface $configLoader;
    private ConfigValidatorInterface $configValidator;
    private ConfigGeneratorInterface $configGenerator;
    private TemplateRendererInterface $templateRenderer;
    private FileManagerInterface $fileManager;
    private LoggerInterface $logger;

    public function __construct(
        ConfigLoaderInterface $configLoader,
        ConfigValidatorInterface $configValidator,
        ConfigGeneratorInterface $configGenerator,
        TemplateRendererInterface $templateRenderer,
        FileManagerInterface $fileManager,
        LoggerInterface $logger
    ) {
        $this->configLoader = $configLoader;
        $this->configValidator = $configValidator;
        $this->configGenerator = $configGenerator;
        $this->templateRenderer = $templateRenderer;
        $this->fileManager = $fileManager;
        $this->logger = $logger;
    }

    /**
     * @param array $options
     * @return ConfigBuilder
     * @throws Exception
     */
    public function create(array $options = []): ConfigBuilder
    {
        return new ConfigBuilder(
            $this->configLoader,
            $this->configValidator,
            $this->configGenerator,
            $this->templateRenderer,
            $this->fileManager,
            $this->logger,
            $options
        );
    }
}
