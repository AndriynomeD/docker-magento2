<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Console\Command;

use DockerBuilder\Core\Builder\ConfigBuilderFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BuildCommand extends Command
{
    protected static $defaultName = 'build';

    private ConfigBuilderFactory $configBuilderFactory;

    /**
     * @param ConfigBuilderFactory $configBuilderFactory
     */
    public function __construct(ConfigBuilderFactory $configBuilderFactory)
    {
        parent::__construct();
        $this->configBuilderFactory = $configBuilderFactory;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this
            ->setName('build')
            ->setDescription('Build Docker environment from configuration')
            ->setHelp('This command allows you to build Docker environment files from templates')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run in dry-run mode (create files in separate directories for comparison)'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $options = [
            'dry_run' => $input->getOption('dry-run'),
            'verbose' => $output->getVerbosity()
        ];

        try {
            $configBuilder = $this->configBuilderFactory->create($options);
            $configBuilder->run();

            return Command::SUCCESS;
        } catch (\Exception $e) {
//            $output->writeln('<error>Build failed: ' . $e->getMessage() . '</error>');
//            if ($output->getVerbosity()) {
//                $output->writeln('<comment>Stack trace:</comment>');
//                $output->writeln($e->getTraceAsString());
//            }
            return Command::FAILURE;
        }
    }
}
