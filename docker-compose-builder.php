<?php

/**
 * Class DockerComposeBuilder
 *
 * Builds files from given configuration and source templates.
 *
 * This extends from the original Builder in the `docker-magento` repository.
 */
class DockerComposeBuilder
{
    const DEFAULT_CONFIG_KEY = "docker-compose";
    const DEFAULT_CONFIG_FILE_NAME = "config.json";
    const DEFAULT_CONFIG_FILE = __DIR__ . DIRECTORY_SEPARATOR . self::DEFAULT_CONFIG_FILE_NAME;
    const DEFAULT_TEMPLATE_FILE = __DIR__ . DIRECTORY_SEPARATOR . "src" . DIRECTORY_SEPARATOR . "docker-compose.tml";
    const DEFAULT_DESTINATION_FILE = __DIR__ . DIRECTORY_SEPARATOR . "docker-compose.yml";
    const DEFAULT_EXECUTABLE_PERMISSIONS = 0755;
    const DEFAULT_VERBOSE_LEVEL = 1;
    
    /**
     * Build targets and their configuration.
     *
     * @var array
     */
    protected $build_config = [];
    
    /**
     * Directory to load template files from.
     *
     * @var string
     */
    protected $template_file;
    
    /**
     * Destination directory for generated files.
     *
     * @var string
     */
    protected $destination_file;
    
    /**
     * File permissions for executable files.
     *
     * @var int
     */
    protected $executable_permissions;
    
    /**
     * Verbosity level.
     *
     * @var int
     */
    protected $verbose_level;
    
    public function __construct($options = [])
    {
        $this->template_file = $options["template_file"] ?? static::DEFAULT_TEMPLATE_FILE;
        $this->destination_file = $options["destination_file"] ?? static::DEFAULT_DESTINATION_FILE;
        $this->executable_permissions = $options["executable_file_permissions"] ?? static::DEFAULT_EXECUTABLE_PERMISSIONS;
        $this->verbose_level = $options["verbose"] ?? static::DEFAULT_VERBOSE_LEVEL;

        $this->loadConfig($options["config_file"] ?? static::DEFAULT_CONFIG_FILE);
    }
    
    /**
     * Build the files described in the loaded config.
     */
    public function run()
    {
        if ($this->build_config) {
            $this->verbose(sprintf("Building '%s'...", $this->destination_file), 1);
            $contents = "";

            if ($template_file = $this->getTemplateFile($this->template_file)) {
                $variables = $this->build_config;
                $contents = $this->renderTemplate($template_file, $variables);
                $contents = str_replace('{{generated_by_builder}}', 'This file is automatically generated. Do not edit directly.', $contents);
            }

            $this->verbose(sprintf("\tWriting '%s'...", $this->destination_file), 2);
            $this->writeFile($this->destination_file, $contents);
            $this->setFilePermissions($this->destination_file, $this->executable_permissions);
        }
    }
    
    /**
     * Load the build configuration from the given file.
     *
     * @param string $file
     *
     * @return $this
     * @throws Exception
     */
    protected function loadConfig($file)
    {
        if (!(file_exists($file) && is_readable($file))) {
            throw new Exception(sprintf("File %s not exist or not readable!", self::DEFAULT_CONFIG_FILE_NAME));
        }

        $config = json_decode(file_get_contents($file), true);

        if (!is_array($config)
            || (!array_key_exists(static::DEFAULT_CONFIG_KEY, $config)
                || !is_array($config[static::DEFAULT_CONFIG_KEY]))
        ) {
            throw new Exception(sprintf("Invalid configuration in %s!", $file));
        }

        $this->build_config = $config[static::DEFAULT_CONFIG_KEY];
        
        return $this;
    }

    /**
     * Return the template file name for the given file.
     *
     * @param string $filename
     * @return null|string
     */
    protected function getTemplateFile($filename)
    {
        if (file_exists($filename) && is_readable($filename)) {
            return $filename;
        }

        return null;
    }
    
    /**
     * Render the given template file using the provided variables and return the resulting output.
     *
     * @param string $template_file
     * @param array  $variables
     *
     * @return string
     */
    protected function renderTemplate($template_file, $variables)
    {
        extract($variables, EXTR_OVERWRITE);
        ob_start();
        
        include $template_file;
        
        $output = ob_get_clean();
        
        return $output ?: "";
    }
    
    /**
     * Write the contents to the given file.
     *
     * @param string $file_name
     * @param string $contents
     *
     * @return $this
     * @throws Exception
     */
    protected function writeFile($file_name, $contents)
    {
        $directory = dirname($file_name);
        
        // If the directory doesn't created then try to create the directory.
        if (!is_dir($directory)) {
            // Create the directory, preventing race conditions if another process creates the directory for us.
            if (!@mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new Exception(sprintf("Unable to create directory %s!", $directory));
            }
        }
        
        if (file_put_contents($file_name, $contents) === false) {
            throw new Exception(sprintf("Failed to write %s!", $file_name));
        }
        
        return $this;
    }
    
    /**
     * Update the permissions on the given file.
     *
     * @param string $file_name
     * @param int    $permissions
     *
     * @return $this
     */
    protected function setFilePermissions($file_name, $permissions = 0644)
    {
        chmod($file_name, $permissions);
        
        return $this;
    }
    
    /**
     * Print an informational message to the command line.
     *
     * @param string $message
     * @param int    $level
     * @param bool   $newline
     *
     * @return $this
     */
    protected function verbose($message, $level = 1, $newline = true)
    {
        if ($level <= $this->verbose_level) {
            printf("%s%s", $message, $newline ? PHP_EOL : "");
        }
        
        return $this;
    }
}

/**
 * __MAIN__
 */

$args = getopt("hvq");
$options = [];

if (isset($args["h"])) {
    echo <<<USAGE
Usage: php builder.php [options]

Options:
    -h  Print out this help message.
    -v  Enable verbose output. Can be used multiple times to increase verbosity level.
    -q  Silence informational messages.
USAGE;
    exit;
}

if (isset($args["q"])) {
    $options["verbose"] = 0;
} else if (isset($args["v"])) {
    $options["verbose"] = count($args["v"]);
}
//export XDEBUG_CONFIG="idekey=PHPSTORM"
$builder = new DockerComposeBuilder($options);
$builder->run();