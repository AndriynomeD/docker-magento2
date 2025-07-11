<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Contract;

use DockerBuilder\Core\Builder\MyOutput;

interface LoggerInterface
{
    public const MSG_ERROR = 'error';
    public const MSG_WARNING = 'warning';
    public const MSG_INFO = 'info';
    public const MSG_INFO_LIGHT = 'info_light';
    public const MSG_SUCCESS = 'success';

    public function error(string $message): void;

    public function warning(string $message): void;

    public function info(string $message): void;
    public function infoLight(string $message): void;

    public function success(string $message): void;
    public function message(string $message, string $type = self::MSG_INFO, int $verbosity = MyOutput::VERBOSITY_NORMAL): void;

    /**
     * Sets the verbosity of the output.
     *
     * @param MyOutput::VERBOSITY_* $verbosity
     *
     * @return void
     */
    public function setVerbosity(int $verbosity): void;

    /**
     * Gets the current verbosity of the output.
     *
     * @return MyOutput::VERBOSITY_*
     */
    public function getVerbosity(): int;
}
