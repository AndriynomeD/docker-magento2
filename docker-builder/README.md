# Docker Builder Core

Docker environment builder for Magento 2 development with flexible configuration system.

## Features

- ✅ **Dual execution mode**: Standalone or with Symfony Console
- ✅ **Flexible configuration**: JSON-based configuration with validation
- ✅ **Template system**: Customizable Docker templates
- ✅ **Dry-run mode**: Preview changes before applying
- ✅ **Verbose output**: Detailed logging and progress tracking
- ✅ **PSR-4 autoloading**: Modern PHP standards compliance

## Installation

### Option 1: Standalone Mode (No dependencies)
```bash
# Clone or download the project
git clone <repository-url>
cd <project-directory>

# Run directly
php docker-builder-run --help
```

### Option 2: Symphony Console Mode
```bash
# Clone or download the project
git clone <repository-url>
cd <project-directory>

cd docker-builder
composer install
php bin/console build --help
```

## Usage
```bash
# Via standalone script
php docker-builder-run [options]

# Or use Symfony Cli (composer install)
cd docker-builder
php bin/console build [options]
```

### Available Options

| Option      | Short         | Description                                         |
|-------------|---------------|-----------------------------------------------------|
| `--dry-run` | -             | Create files in separate directories for comparison |
| `--verbose` | `-v\|vv\|vvv` | Enable verbose output                               |
| `--quiet`   | `-q`          | Suppress informational messages                     |
| `--help`    | `-h`          | Show help message                                   |

## Configuration
The builder looks for configuration files in the following order:
1. `docker-builder-config.json` (current directory)

## Project Structure
```
./
├── config.json                     # Custom configuration (createt by user)
├── compose.yaml                    # Docker Compose file (generated)
├── compose-dry-run.yaml            # Dry-run Docker Compose file (generated)
├── docker-builder-run              # Main executable script
├── containers/                     # Folder for docker containers (static & generated)
├── containers-dry-run/             # Dry-run folder for docker containers (generated only)
└── docker-builder/                 # Core package directory
    ├── composer.json               # Composer dependencies
    ├── autoload.php                # Standalone autoloader
    ├── bin/
    │   └── console                 # Symfony Console entry point
    ├── resources/                  # Resources: templates, config samples
    │   ├── templates/              # Templates
    │   │   ├── compose-template.php
    │   │   ├── phpContainers/
    │   │   │   ├── Dockerfile
    │   │   │   └── ...
    │   │   └── search_engine/
    │   │       ├── elasticsearch/
    │   │       └── opensearch/
    │   └── samples/                # Config samples
    │       └── config.json.sample
    └── src/
        ├── Console/                # Symfony Console commands
        │   ├── Application.php
        │   └── Command/
        │       └── BuildCommand.php
        ├── Builder/                # Main business logic
        │   ├── ConfigBuilder.php
        │   └── ConfigBuilderFactory.php
        ├── Config/                 # Configuration handling
        │   ├── ConfigGenerator.php
        │   ├── ConfigGeneratorInterface.php
        │   ├── ConfigValidator.php
        │   ├── ConfigValidatorInterface.php
        │   ├── JsonConfigLoader.php
        │   └── ConfigLoaderInterface.php
        ├── File/                   # File operations
        │   ├── FileManager.php
        │   └── FileManagerInterface.php
        └── Template/               # Template rendering
            ├── TemplateRenderer.php
            └── TemplateRendererInterface.php

```

### Extending Configuration
```php
// Implement ConfigLoaderInterface for custom loaders
namespace DockerBuilder\Core\Config;

use DockerBuilder\Core\Config\ConfigLoaderInterface;

class YamlConfigLoader implements ConfigLoaderInterface
{
    // ... implementation
}
```

## Requirements
- **PHP**: 7.4+ (8.2+ recommended)
- **Composer**: 2.0+ (optional, for full functionality)
- **Symfony Console**: ^6.0 (auto-installed with composer)

## License
MIT License - see LICENSE file for details.
