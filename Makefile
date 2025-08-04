.PHONY: copy-configs build build-dry-run clean-dry-run

DOCKER_BUILDER_DIR := docker-builder
DOCKER_BUILDER_CMD := $(DOCKER_BUILDER_DIR)/bin/console

# Copy sample files with overwrite confirmation
copy-configs:
	_interactive_copy_configs

# Build docker environment based on configuration
build: _check_dependencies _check_configs
	@$(DOCKER_BUILDER_CMD) build $(filter-out $@,$(MAKECMDGOALS))

# Dry-run build docker environment based on configuration
build-dry-run: _check_dependencies _check_configs
	@$(DOCKER_BUILDER_CMD) build --dry-run $(filter-out $@,$(MAKECMDGOALS))

# Remove dry-run files and directories (with confirmation)
clean-dry-run:
	@if [ -d "./containers-dry-run" ] || [ -d "./envs-dry-run" ] || [ -f "./compose-dry-run.yaml" ]; then \
		read -p "Do you really want to delete Dry-run files & folders? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			([ -d "./containers-dry-run" ] && rm -rf ./containers-dry-run && echo "Directory ./containers-dry-run deleted.") || echo "Directory ./containers-dry-run does not exist."; \
			([ -d "./envs-dry-run" ] && rm -rf ./envs-dry-run && echo "Directory ./envs-dry-run deleted.") || echo "Directory ./envs-dry-run does not exist."; \
			([ -f "./compose-dry-run.yaml" ] && rm -f ./compose-dry-run.yaml && echo "File ./compose-dry-run.yaml deleted.") || echo "File ./compose-dry-run.yaml does not exist."; \
		else \
			echo "Deletion cancelled."; \
		fi; \
	else \
		echo "No dry-run files/directories to delete."; \
	fi

_auto_copy_configs:
	@if [ ! -f "./envs/composer.env" ]; then \
		mkdir -p ./envs && cp $(DOCKER_BUILDER_DIR)/resources/samples/envs/composer.env.sample ./envs/composer.env; \
		echo "File ./envs/composer.env created."; \
	fi; \
	if [ ! -f "./envs/global.env" ]; then \
		mkdir -p ./envs && cp $(DOCKER_BUILDER_DIR)/resources/samples/envs/global.env.sample ./envs/global.env; \
		echo "File ./envs/global.env created."; \
	fi; \
	if [ ! -f "./config.json" ]; then \
		cp $(DOCKER_BUILDER_DIR)/resources/samples/config.json.sample ./config.json; \
		echo "File ./config.json created."; \
	fi

_interactive_copy_configs:
	@if [ -f "./envs/composer.env" ]; then \
		read -p "File ./envs/composer.env already exists. Overwrite? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			cp -f $(DOCKER_BUILDER_DIR)/resources/samples/envs/composer.env.sample ./envs/composer.env; \
			echo "File ./envs/composer.env overwritten."; \
		else \
			echo "File ./envs/composer.env left unchanged."; \
		fi; \
	else \
		mkdir -p ./envs && cp $(DOCKER_BUILDER_DIR)/resources/samples/envs/composer.env.sample ./envs/composer.env; \
		echo "File ./envs/composer.env created."; \
	fi

	@if [ -f "./envs/global.env" ]; then \
		read -p "File ./envs/global.env already exists. Overwrite? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			cp -f $(DOCKER_BUILDER_DIR)/resources/samples/envs/global.env.sample ./envs/global.env; \
			echo "File ./envs/global.env overwritten."; \
		else \
			echo "File ./envs/global.env left unchanged."; \
		fi; \
	else \
		mkdir -p ./envs && cp $(DOCKER_BUILDER_DIR)/resources/samples/envs/global.env.sample ./envs/global.env; \
		echo "File ./envs/global.env created."; \
	fi

	@if [ -f "./config.json" ]; then \
		read -p "File ./config.json already exists. Overwrite? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			cp -f $(DOCKER_BUILDER_DIR)/resources/samples/config.json.sample ./config.json; \
			echo "File ./config.json overwritten."; \
		else \
			echo "File ./config.json left unchanged."; \
		fi; \
	else \
		cp $(DOCKER_BUILDER_DIR)/resources/samples/config.json.sample ./config.json; \
		echo "File ./config.json created."; \
	fi

_check_dependencies:
	@if [ ! -f "$(DOCKER_BUILDER_DIR)/composer.json" ]; then \
		echo "Error: composer.json not found in $(DOCKER_BUILDER_DIR)"; \
		exit 1; \
	fi; \
	if [ ! -f "$(DOCKER_BUILDER_DIR)/vendor/autoload.php" ]; then \
		echo "Installing composer dependencies in $(DOCKER_BUILDER_DIR)..."; \
		cd $(DOCKER_BUILDER_DIR) && composer install 2>&1; \
		if [ $$? -ne 0 ]; then \
			echo "Error: Composer install failed"; \
			exit $$?; \
		fi; \
		echo "Composer install completed successfully."; \
	fi

_check_configs:
	@if [ ! -f "./envs/global.env" ]; then \
		echo "Error: ./envs/global.env not found. Please run 'make copy-configs'"; \
		exit 1; \
	fi; \
	if [ ! -f "./envs/composer.env" ]; then \
		echo "Error: ./envs/composer.env not found. Please run 'make copy-configs'"; \
		exit 1; \
	fi; \
	if [ ! -f "./config.json" ]; then \
		echo "Error: ./config.json not found. Please run 'make copy-configs'"; \
		exit 1; \
	fi

# rule patter for cmd args
%:
	@:


