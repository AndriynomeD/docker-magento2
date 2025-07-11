<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Contract;

use Exception;

/**
 * Interface for configuration validation
 */
interface ConfigValidatorInterface
{
    /**
     * Validate entire configuration
     * @param array $config
     * @throws Exception
     */
    public function validate(array $config): void;
}
