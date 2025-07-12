.PHONY: copy-configs clean-dry-run build-dry-run

# Copy sample files with overwrite confirmation
copy-configs:
	@if [ -f "./envs/composer.env" ]; then \
		read -p "File ./envs/composer.env already exists. Overwrite? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			cp -f ./docker-builder/resources/samples/envs/composer.env.sample ./envs/composer.env; \
			echo "File ./envs/composer.env overwritten."; \
		else \
			echo "File ./envs/composer.env left unchanged."; \
		fi; \
	else \
		mkdir -p ./envs && cp ./docker-builder/resources/samples/envs/composer.env.sample ./envs/composer.env; \
		echo "File ./envs/composer.env created."; \
	fi

	@if [ -f "./envs/global.env" ]; then \
		read -p "File ./envs/global.env already exists. Overwrite? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			cp -f ./docker-builder/resources/samples/envs/global.env.sample ./envs/global.env; \
			echo "File ./envs/global.env overwritten."; \
		else \
			echo "File ./envs/global.env left unchanged."; \
		fi; \
	else \
		mkdir -p ./envs && cp ./docker-builder/resources/samples/envs/global.env.sample ./envs/global.env; \
		echo "File ./envs/global.env created."; \
	fi

	@if [ -f "./config.json" ]; then \
		read -p "File ./config.json already exists. Overwrite? (y/n) " yn; \
		if [ "$$yn" = "y" ]; then \
			cp -f ./docker-builder/resources/samples/config.json.sample ./config.json; \
			echo "File ./config.json overwritten."; \
		else \
			echo "File ./config.json left unchanged."; \
		fi; \
	else \
		cp ./docker-builder/resources/samples/config.json.sample ./config.json; \
		echo "File ./config.json created."; \
	fi

# Run dry build process
build-dry-run:
	php docker-builder-run --dry-run

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


