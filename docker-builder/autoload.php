<?php
/**
 * Simple autoloader for DockerBuilder\Core namespace
 */

spl_autoload_register(function ($className) {
    $prefix = 'DockerBuilder\\Core\\';
    $baseDir = __DIR__ . '/src/';

    // Check if the class uses our namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $className, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($className, $len);
    /* skip symphony-related classes */
    if (strpos($relativeClass, 'Console\\') === 0) {
        return;
    }
    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

