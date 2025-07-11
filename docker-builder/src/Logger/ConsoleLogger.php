<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Logger;

use DockerBuilder\Core\Builder\MyOutput;
use DockerBuilder\Core\Contract\LoggerInterface;

class ConsoleLogger implements LoggerInterface
{
    private const COLORS = [
        self::MSG_ERROR => "\033[1;37m\033[0;31m",
        self::MSG_WARNING => "\033[1;37m\033[1;33m",
        self::MSG_INFO => "\033[1;37m\033[1;34m",
        self::MSG_INFO_LIGHT => "\033[1;37m\033[0;37m",
        self::MSG_SUCCESS => "\033[1;37m\033[1;32m",
    ];

    private const COLOR_RESET = "\033[0m";

    private int $verbosity;

    public function __construct(int $verbosity = MyOutput::VERBOSITY_NORMAL)
    {
        $this->verbosity = $verbosity;
    }

    public function error(string $message): void
    {
        $this->message($message, self::MSG_ERROR, MyOutput::VERBOSITY_QUIET);
    }

    public function warning(string $message, int $verbosity = MyOutput::VERBOSITY_NORMAL): void
    {
        $this->message($message, self::MSG_WARNING, $verbosity);
    }

    public function info(string $message, int $verbosity = MyOutput::VERBOSITY_NORMAL): void
    {
        $this->message($message, self::MSG_INFO, $verbosity);
    }

    public function infoLight(string $message, int $verbosity = MyOutput::VERBOSITY_NORMAL): void
    {
        $this->message($message, self::MSG_INFO_LIGHT, $verbosity);
    }

    public function success(string $message, int $verbosity = MyOutput::VERBOSITY_NORMAL): void
    {
        $this->message($message, self::MSG_SUCCESS, $verbosity);
    }

    public function message(string $message, string $type = self::MSG_INFO, int $verbosity = MyOutput::VERBOSITY_NORMAL): void
    {
        $this->verbose($this->formatMessage($message, $type), $verbosity);
    }

    public function setVerbosity(int $verbosity): void
    {
        $this->verbosity = $verbosity;
    }

    public function getVerbosity(): int
    {
        return $this->verbosity;
    }


    private function formatMessage(string $message, string $type = self::MSG_INFO): string
    {
        $color = self::COLORS[$type] ?? self::COLORS[self::MSG_INFO];
        return $color . $message . self::COLOR_RESET;
    }

    private function verbose(string $message, int $verbosity = MyOutput::VERBOSITY_NORMAL): void
    {
        if ($message !== '' && $verbosity <= $this->getVerbosity()) {
            printf("%s%s", $message, PHP_EOL);
        }
    }
}
