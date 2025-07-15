<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Logger;

use Symfony\Component\Console\Output\OutputInterface;

interface LoggerInterface
{
    public const MSG_ERROR = 'error';
    public const MSG_WARNING = 'warning';
    public const MSG_INFO_BOLT = 'info_bolt';
    public const MSG_INFO = 'info';
    public const MSG_SUCCESS = 'success';

    public function error(string $message): void;

    public function warning(string $message): void;

    public function infoBolt(string $message): void;
    public function info(string $message): void;

    public function success(string $message): void;
    public function message(string $message, string $type = self::MSG_INFO_BOLT, int $verbosity = OutputInterface::VERBOSITY_NORMAL): void;

    /**
     * Sets the verbosity of the output.
     *
     * @param OutputInterface::VERBOSITY_* $verbosity
     *
     * @return void
     */
    public function setVerbosity(int $verbosity): void;

    /**
     * Gets the current verbosity of the output.
     *
     * @return OutputInterface::VERBOSITY_*
     */
    public function getVerbosity(): int;
}
