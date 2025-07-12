<?php

declare(strict_types=1);

namespace DockerBuilder\Core\File;

use Exception;

/**
 * File Manager
 */
class FileManager implements FileManagerInterface
{
    private int $defaultPermissions;

    public function __construct(int $defaultPermissions = 0644)
    {
        $this->defaultPermissions = $defaultPermissions;
    }

    /**
     * Write content to a file
     * @param string $path
     * @param string $content
     * @throws Exception
     */
    public function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            $this->createDirectory($directory);
        }

        $result = file_put_contents($path, $content);
        if ($result === false) {
            throw new Exception(sprintf("Failed to write file %s", $path));
        }
    }

    /**
     * Create a directory if not exists
     * @param string $path
     * @throws Exception
     */
    public function createDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        $result = mkdir($path, 0755, true);
        if (!$result) {
            throw new Exception(sprintf("Failed to create directory %s", $path));
        }
    }

    /**
     * Set file permissions
     * @param string $path
     * @param int|null $permissions
     * @throws Exception
     */
    public function setPermissions(string $path, ?int $permissions = null): void
    {
        if (!file_exists($path)) {
            throw new Exception(sprintf("File %s does not exist", $path));
        }

        $permissions = $permissions ?? $this->defaultPermissions;
        $result = chmod($path, $permissions);
        if (!$result) {
            throw new Exception(sprintf("Failed to set permissions for %s", $path));
        }
    }
}
