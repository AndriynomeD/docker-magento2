<?php

declare(strict_types=1);

namespace DockerBuilder\Core\Contract;

use Exception;

/**
 * Interface for file operations
 */
interface FileManagerInterface
{
    /**
     * Write content to a file
     * @param string $path
     * @param string $content
     * @throws Exception
     */
    public function writeFile(string $path, string $content): void;

    /**
     * Create a directory if not exists
     * @param string $path
     * @throws Exception
     */
    public function createDirectory(string $path): void;

    /**
     * Set file permissions
     * @param string $path
     * @param int $permissions
     * @throws Exception
     */
    public function setPermissions(string $path, int $permissions): void;
}
