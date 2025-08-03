# Magento 2 Docker

Docker infrastructure for managing multiple Magento 2 projects via nginx-proxy with simultaneous access to all instances.
Useful for Magento 2 developers.

Magento 2 Docker infrastructure have the following structure:
```
nginx-proxy & shared infrastructure
    ├── project1 infrastructure
    ├── project2 infrastructure
    ├── ...
    ├── projectN infrastructure
    └── ...
```

Inspired by [meanbee/docker-magento2][meanbee-docker-magento2]

For Max OS X useful: https://www.meanbee.com/developers/magento2-development-procedure.html  

### Usage 

1) Install & Configure [docker-magento2-shared-infra][docker-magento2-shared-infra]
2) Prepare all config files:
   1) Run `make copy-configs` - it will create config files from samples (will create `./envs/composer.env`, `./envs/global.env`, `config.json`).
   ```shell
   make copy-configs
   ```
   2) Fill COMPOSER_MAGENTO_USERNAME, COMPOSER_MAGENTO_PASSWORD, POSTFIX_SASL_PASSWD with you're data in `./envs/composer.env`, `./envs/global.env`.
       Example of required field for `./envs/composer.env` file:
       ```env
       COMPOSER_MAGENTO_USERNAME={{repo.magento.com_username}}
       COMPOSER_MAGENTO_PASSWORD={{repo.magento.com_password}}
       ``` 
       P.S. For existing project `{{repo.magento.com_username}}` & `{{repo.magento.com_password}}` for `composer.env` can be found inside `{{magento_root}}/auth.json`
   3) In `config.json` update sections with you're data (read 'Single-store', 'Multi-store', 'Grunt' sections first), also check [magento 2 system requirements][magento-system-requirements]:
      
      | Option name                 | Description                                                                                                                                                  |
      |-----------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------|
      | M2_PROJECT                  | Project name (`{{project_name}}`) used to fill container hostname, etc. Example: magento2.docker                                                             |
      | M2_VIRTUAL_HOSTS            | Comma separated list of all site domains (`{{all_site_domain}}`). See `Single-store` or `Multi-store` section. `mkcert` will automatically create ssl certs. |
      | M2_DB_NAME                  | Database name (`{{database_name}}`) with pattern: `{{client}}_{{project-name}}_{{dump-date}}`. Example: someclient_someproject_20190710                      |
      | PHP_VERSION                 | PHP version, available: 7.0, 7.1, 7.2, 7.3, 7.4, 8.0, 8.1, 8.2, 8.3, 8.4                                                                                     |
      | M2_EDITION                  | Magento edition. Options: community, enterprise, cloud, mage-os                                                                                              |
      | M2_VERSION                  | Magento 2 version                                                                                                                                            |
      | M2_SOURCE_VOLUME            | Magento root folder `{{magento_root}}`. Default: "./magento", example: "./source/src"                                                                        |
      | M2_INSTALL:                 | `magento-installer` config section                                                                                                                           |
      | ├── BASE_URL                | Main domain "http://`{{main_domain}}`/" or "https". See `Single-store` or `Multi-store` section                                                              |
      | ├── SECURE_BASE_URL         | Main domain "https://`{{main_domain}}`/". See `Single-store` or `Multi-store` section                                                                        |
      | ├── INSTALL_DB              | Should `magento-installer` install database.                                                                                                                 |
      | ├── USE_SAMPLE_DATA         | Should `magento-installer` install magento sample data. Available: true, false, venia                                                                        |
      | ├── ADMIN_EMAIL             | Recommended use real email because starting from 2.4.0+ magento used 2FA by default                                                                          |
      | └── CRYPT_KEY               | if not empty `magento-installer` will us this key else will generate random                                                                                  |
      | M2_SETTINGS:                | Magento additional services settings                                                                                                                         |
      | ├── SEARCH_ENGINE_SETTINGS  | Search engine settings (Required unique index-prefix). If `search_engine`==false or `search_engine/enabled`==false will be ignore.                           |
      | ├── AMQ_SETTINGS            | AMQ settings. If `rabbitmq`==false will be ignore                                                                                                            |
      | ├── REDIS_SETTINGS          | Redis settings. If `redis`==false or `redis/enabled`==false will be ignore.                                                                                  |
      | └── VARNISH_SETTINGS        | Varnish setting. If `varnish`==false will be ignore                                                                                                          |
      | DOCKER_SERVICES:            |                                                                                                                                                              |
      | ├── database:               |                                                                                                                                                              |
      | │   ├── TYPE:               | Service type. Options: mariadb, mysql, percona                                                                                                               |
      | │   ├── VERSION:            | Service version.                                                                                                                                             |
      | │   ├── IMAGE:              | Optional. Service docker image. If not set, image will be defined based on TYPE.                                                                             |
      | │   ├── TAG:                | Optional. Service docker image tag. If not set, tag will be defined based on VERSION.                                                                        |
      | │   └── VOLUME:             | Folder under mysql_volumes` that will be mounted to db container                                                                                             |
      | ├── search_engine:          | Use search engine service? Options: false, {}                                                                                                                |
      | │   ├── enabled:            | Is Service enabled? Options: true, false                                                                                                                     |
      | │   ├── CONNECT_TYPE:       | Options: external (use shared search engine), internal (create separate container for project)                                                               |
      | │   ├── TYPE:               | Service type. Options: elasticsearch, opensearch                                                                                                             |
      | │   ├── VERSION:            | Service version. If `CONNECT_TYPE`!=internal will be ignore                                                                                                  |
      | │   ├── IMAGE:              | Optional. Service docker image. If not set, image will be defined based on TYPE.                                                                             |
      | │   └── TAG:                | Optional. Service docker image tag. If not set, tag will be defined based on VERSION.                                                                        |
      | ├── varnish                 | Use varnish service? Options: false, true                                                                                                                    |
      | ├── cron                    | Use cron service? Options: false, true                                                                                                                       |
      | ├── redis                   | Use redis service? Options: false, {}                                                                                                                      |
      | │   ├── enabled:            | Is Service enabled? Options: true, false                                                                                                                     |
      | │   ├── TYPE:               | Service type. Options: redis, valkey                                                                                                                         |
      | │   ├── VERSION:            | Service version.                                                                                                                                             |
      | │   ├── IMAGE:              | Optional. Service docker image. If not set, image will be defined based on TYPE.                                                                             |
      | │   └── TAG:                | Optional. Service docker image tag. If not set, tag will be defined based on VERSION.                                                                        |
      | ├── rabbitmq                | Use rabbitmq service? Options: false, true                                                                                                                   |
      | ├── magento-coding-standard | Create separate MCS container (need if project not contain latest MCS version: typical cloud and CE/EE before ver.2.4.4). Available options: false, true     |
      | └── venia                   | Install `venia` PWA and use magento as backend? Available options: false, true [Currently just install venia sample data]                                    |

      Section `php-containers` contain configuration for php-fpm, cli, msc container for each php version.
      Now `"composerVersion": "latest"` will set version 2.X for Magento 2.3.7 & 2.4.2+, in another case will set 1.10.17


3) Build magento 2 docker infrastructure:
    ```shell
    make build
    ```
4) Make directory `{{magento_root}}` inside project root directory `{{root_directory}}`. 

5) Up containers (first time up better don't add key `-d` for check if all okay, `--remove-orphans` - required, because latest compose doesn't always properly remove containers):
    ```shell
    docker compose up -d --remove-orphans
    ```

6) Clone existing repo with magento into `{{magento_root}}` folder or run next command for install magento from scratch:
    ```shell
    docker compose run --rm cli magento-installer
    ```
7) Now you can enter to cli (you should run all magento commands under cli).
   P.S. Instead of `php bin/magento` use `sudo -uwww-data php bin/magento`:
   Example :
    ```shell
    docker compose run --rm cli bash
    ```
   
8) If you clone  existing repo import database:
    1) Copy database dump into `{{magento_root}}`.
    2) Go to cli-container & import database dump:
	    ```shell
	    docker compose run --rm cli bash
	    ```
	    Import dump:
	    ```shell
	    mysql -hdb -umagento2 -p {{youre_database_name}} < {{youre_database_dump}}
	    ```
	    Or using source:
	    ```shell
	    mysql -hdb -umagento2 -p 
	    use {{youre_database_name}};
	    source {{youre_database_dump}}
	    ```
	    Copy into right places under `{{magento_root}}` all magento required secure sensitive file like `app/etc/env.php` file.  
	    In `env.php` file used next config for a database:
	    ```php
	    'host' => 'db',
	    'dbname' => `{{database_name}}`,
	    'username' => 'magento2',
	    'password' => 'magento2',
	    ```
	    Maybe after you want to create admin user:
	    ```shell
	    sudo -uwww-data php bin/magento admin:user:create --admin-user="admin" --admin-password="admin123" --admin-email="admin@example.com" --admin-firstname="AdminFirstName" --admin-lastname="AdminLastName"
	    ```
    3) Update env.php with service configs (also need to do after updating additional docker services)
    ```shell
    magento-service-updater
    ```

### Single-store

1) `config.json` params:
    1) `{{all_site_domain}}` same as `{{main_domain}}` (example: someproject.site)
2) Add all you're site domain to `/etc/hosts` file:
    ```hosts 
    # single-store sites (docker):
    ...
    127.0.0.1 {{main_domain}}
    ...
    # end single-store sites (docker)
    ```
    Example:
    ```hosts
    # single-store sites (docker):
    127.0.0.1 some_anotherproject1.site
    127.0.0.1 some_anotherproject2.site
    127.0.0.1 someproject.site
    127.0.0.1 some_anotherproject3.site
    # end single-store sites (docker)
    ```

### Multi-store
Example: we have 3 store/website: someproject.site, someproject-vip.site, someproject-retail.site
1) `config.json` params:
    1) `{{all_site_domain}}` - comma separated all site domains (example: `someproject.site,someproject-vip.site,someproject-retail.site`)
    2) `{{main_domain}}` - So you should choose one domain main (example: we choose `someproject.site` like a `{{main_domain}}`)
2) In folder `{{root_directory}}/nginx/etc/multi_vhost/` create one or ultiple own config file(s) for multi-store. Use file `example_vhost.conf` as example.
3) Add space separated all you're site domain to `/etc/hosts` file:
    ```hosts
    # multi-store {{unique project name or main_domain or unique number}} (docker):
    127.0.0.1 {{main_domain}} {{additional_domain_1}} ... {{additional_domain_N}}
    # end multi-store {{unique project name or number}} (docker)
    ```
    Example:
    ```hosts
    # multi-store 9 (docker)
    127.0.0.1 someproject.site someproject-vip.site someproject-retail.site
    127.0.0.1 some_anotherproject2.site some_anotherproject2-vip.site some_anotherproject2-retail.site
    # end multi-store 9 (docker)
    ```

### Grunt

For use grunt need prepared magento grunt config file & init npm inside `{{magento_root}}` using cli-container.  

Rename (create renamed copy) the following files in your Magento `{{magento_root}}`:
1. package.json.sample to package.json
2. Gruntfile.js.sample to Gruntfile.js
Rename (create renamed copy) the following file:
3. `{{magento_root}}/dev/tools/grunt/configs/themes.js` to `{{magento_root}}/dev/tools/grunt/configs/local-themes.js`
4. Update local-themes.js by include your local site theme
5. Inside `{{root_directory}}`: 
    ```shell
    docker compose run --rm cli bash
    npm install
    npm update
    ```
    
    Then in bash of cli-container got to magento root directory and use standard grunt command:
    ```shell
    sudo -uwww-data grunt clean   # Removes the theme related static files in the pub/static and var directories.
    sudo -uwww-data grunt exec    # Republishes symlinks to the source files to the pub/static/frontend/ directory.
    sudo -uwww-data grunt less    # Compiles .css files using the symlinks published in the pub/static/frontend/ directory.
    sudo -uwww-data grunt watch   # Tracks the changes in the source files, recompiles .css files, and reloads the page in the browser.
    ```
    Example of run grunt watch:
    ```shell
    docker compose run --rm cli bash
    sudo -uwww-data grunt exec:all && sudo -uwww-data grunt less
    sudo -uwww-data grunt watch
    ```

P.S. LiveReloads the page in the browser not working.  
Also `Warning: Error compiling lib/web/css/docs/source/docs.less Use --force to continue.` - it's magento native bug. 

### PphStorm

1) #### PphStorm Magento plugin:
    1. Install & Enable the official Magento plugin for PphStorm.
    2. Enabled plugin for the project in Settings->PHP->Frameworks->Magento
    3. Config Project PHP CLI Interpreter: 
    ```plaintext
    Settings->Directories->Excluded files: *Test*
    P.S. not work with vendor/*
    ```
    ```plaintext
    Settings->PHP
        PHP Language level: `PHP_VERSION`
        CLI Interpreter: click '...' -> click '+' -> choose 'From Docker,...'
            Config Remote PHP Interpreter:
                choose 'Docker Compose'
                Name: 'cli'
                Service: 'cli'
        Path mapping: map `{{magento_root}}` in left column to path `/var/www/magento` inside container.
    ```
   
2) #### Xdebug config:
    
    1. Enabled Xdebug in `./envs/global.env` file (`PHP_XDEBUG_MODE=develop,debug,coverage` - can be single mode or multimode).
    2. `Add Configuration` or `Edit Configuration`
    3. Add `PHP remote debug`
        ```plaintext
        Name: Configuration
        IDE key(s): PHPSTORM
        Server: 
            Name: Configuration
            host: localhost
            port: 80
            Debuger: Xdebug
            Use path mapping: Yes
                map `{{magento_root}}` in left column to path `/var/www/magento` inside container
        ```
    4. Apply this config
    
    For cli debug:
    ```shell
    sudo su -l www-data -s /bin/bash
    cd /var/www/magento/ && export XDEBUG_CONFIG="remote_host=host.docker.internal"
    php bin/magento setup:up    # command for debug example
    exit
    ```
   ```shell
    sudo su -l www-data -s /bin/bash
    cd /var/www/magento/ && export XDEBUG_CONFIG="client_host=host.docker.internal"
    php bin/magento setup:up    # command for debug example
    exit
    ```
    

3) #### [Magento Coding Standard][magento-coding-standard]
    Currently, CE/EE ver.2.4.4+ contain one of the latest 'MCS' package version, so not need use separate 'MCS' container for it.
    1. If used separate 'MCS' container: on "Prepare all config files" step in `config.json` set `magento-coding-standard` under `DOCKER_SERVICES` to `true`.
    2. Currently, PhpStorm don't have docker connection for eslint, so you need install npm on host machine.
    3. If used separate 'MCS' container: install Magento Coding Standard project
        ```shell
        docker compose run --rm mcs magento-coding-standard-installer
        cd `{{root_directory}}/magento-coding-standard`
        npm init
        ```
    4. Config PhpStorm (after magento, magento-coding-standard projects (optional) was setup):
        - If used separate 'MCS' container need create 'MCS' CLI Interpreter
        ```plaintext
        Settings->Languages & Frameworks->PHP->Quality tools
            PHP_CodeSniffer:
                Configuration: click '...'-> 
                     PHP_CodeSniffer: click '+' -> 
                         PHP_CodeSniffer By Interpreter: click '...'-> 
                             CLI Interpreters: click '+' -> choose 'From Docker,...'
                                Config Remote PHP Interpreter:
                                    choose 'Docker Compose'
                                    Name: 'mcs'
                                    Service: 'mcs'
                     Path mapping: map <Project root>/magento-coding-standard->/var/www/magento-coding-standard.
                     PHP_CodeSniffer path: `/var/www/magento-coding-standard/vendor/bin/phpcs`
                     Path to phpcbf: `/var/www/magento-coding-standard/vendor/bin/phpcbf`
                Check files with extensions: 'php,js,css,inc,phtml'
                Show sniff name: Yes
                Coding Standard: Magento2 (if not see press reload & scroll up list)
                Enabled PHP_CodeSniffer by put swith to 'Yes'
        Settings->Editor->Inspection
            PHP->Quality tools
                ->PHP_CodeSniffer validation: Activate
        ```
        - If used 'MCS' package from magento project just use 'cli' container (see 'PphStorm Magento plugin' section):
         ```plaintext
        Settings->Languages & Frameworks->PHP->Quality tools
            PHP_CodeSniffer:
                Configuration: choose 'cli' & click '...'-> 
                    PHP_CodeSniffer: click '+' -> 
                        PHP_CodeSniffer By Interpreter: choose 'cli'
                    Path mapping: already filled.
                    PHP_CodeSniffer path: `/var/www/magento/vendor/bin/phpcs`
                    Path to phpcbf: `/var/www/magento/vendor/bin/phpcbf`
                Check files with extensions: 'php,js,css,inc,phtml'
                Show sniff name: Yes
                Coding Standard: Magento2 (if not see press reload & scroll up list)
                Enabled PHP_CodeSniffer by put swith to 'Yes'
        Settings->Editor->Inspection
            PHP->Quality tools
                ->PHP_CodeSniffer validation: Activate
        ```
       - Configure Mess Detector:
        ```plaintext
        Settings->Languages & Frameworks->PHP->Quality tools
            Mess Detector:
                Configuration: choose 'cli' & click '...'-> 
                    Mess Detector: click '+' -> 
                         Mess Detector By Interpreter: choose 'cli'
                    Path mapping: already filled.
                    PHP Mess Detector path: already filled.
                Choose all 'Options'
                Add custom ruleset:
                path: {{absolute_path}}/magento/dev/tests/static/testsuite/Magento/Test/Php/_files/phpmd/ruleset.xml'
                Enabled PHP_CodeSniffer by put swith to 'Yes'
        Settings->Editor->Inspection
            PHP->Quality tools
                ->PHP Mess Detector validation: Activate 
        ```
        - Configure ESlint:
        ```plaintext
        Settings->Languages & Frameworks
        JavaScript->Code Quality Tools->ESLint:
            Manual ESLint configuration:
                ESLint package: {{absolute_path}}/magento-coding-standard/node_modules/eslint
                Configuration file: {{absolute_path}}/magento-coding-standard/eslint/.eslintrc
                Additional rules directory: {{absolute_path}}/magento-coding-standard/eslint/rules
        ```

    For upgrade magento coding standard enter inside mcs container:
    ```shell
    docker compose run --rm mcs bash
    <!-- upgrade comands -->
    ...
    ```

### Ngrok support (usefully for testing online payment methods etc.)

1) [Install ngrok](https://dashboard.ngrok.com/get-started/setup/linux). Copy `ngrok` to `/usr/local/bin` for run ngrok from any folder by command 'ngrok'.
2) Download & install [magento ngrok extension][magento-ngrok] to app/code/Shkoliar/Ngrok folder.
3) Run to cli container and set `sudo -uwww-data php bin/magento config:set --lock-env web/url/redirect_to_base 0`.
4) Redeploy project: set:up, s:d:c, etc.
5) Run ngrok with additional param host-header. Example `ngrok http https://magento248.site --host-header=magento248.site`:
   P.S. `{{BASE_URL}}` === `http://{{main_domain}}/` or `https://{{main_domain}}/`
    ```shell
    ngrok http {{BASE_URL}} --host-header={{main_domain}} # tested with http/https as `{{BASE_URL}}`
    ```
    How it works: internet->ngrok->reverse-proxy->project-entrypoint(nginx/varnish->nginx->varnish)->reverse-proxy->ngrok->internet

    P.S. It for local development - do not commit Shkoliar_Ngrok into project git.
    Alternative:
    ```shell
    ngrok http --host-header={{local_site_domain}} 80         # work for http only
    ngrok http {{BASE_URL}}:80 --host-header={{main_domain}}  # additional specified port
    ngrok http {{BASE_URL}}:443 --host-header={{main_domain}} # additional specified port
    ```

### Maybe useful

1) For debug inside 'ubuntu' container (docker exec -it {{container}} bash):
    ```shell
    sudo apt-get install nano
    sudo apt-get install rsyslog
    sudo apt-get install telnet
    sudo apt-get install dnsutils
    sudo apt-get install iputils-ping
    ```
2) For debug inside 'alpine' container (docker exec -it {{container}} sh):
    ```shell
    yum install iputils
    ``` 
3) In Mysql:
    ```shell
    mysql -hdb -umagento2 -p
    ```
    ```sql
    show databases;
    ```
    ```sql
    create database {{youre_database_name}};
    ```
    ```sql
    GRANT ALL PRIVILEGES ON {{youre_database_name}}.* TO 'magento2'@'%'; - add user `magento2` grant for {{youre_database_name}}
    ```
    ```sql
    use {{youre_database_name}};
    ```
    ```sql
    source {{youre_database_dump}}
    ```
    Change password:
    ```sql
    UPDATE mysql.user SET Password=PASSWORD('root') WHERE User='root';
    FLUSH PRIVILEGES;
    ```

4) PphStorm & Database
    Get real db host & port (this info can be find using next command):
    ```shell
    docker ps -a
    ```
    Next use it when connected to db by PphStorm


### Problem

If you can't edit the magento files in PhpStorm, try it:
```shell
    sudo usermod -aG www-data ${USER}
    sudo chmod -R g+w magento
```
Fix problem with owner:
```shell
    sudo chown -R www-data:www-data var/cache
```
Example of fix permission problem inside cli-container:
```shell
    sudo chown -R www-data:www-data $MAGENTO_ROOT && sudo chmod -R g+w $MAGENTO_ROOT && rm -rf var/cache && rm -rf var/page_cache && rm -rf var/generation && rm -rf var/session
```

### [WIP] Venia Support
- [x] Implement https functional.
- [x] Implement Venia Sample Data.
- [ ] Implement configuration for Magento PWA.
Because Venia required worked connection to magento 2 think at least as next step is create separate docker-compose.yml in `{{root_directory}}/magento_venia` directory. PWA project should be initiated in `{{root_directory}}/magento_venia/source`. In Venia `DEV_SERVER_HOST`==`{{main_domain}}`, `DEV_SERVER_PORT`==`443`.
Also need to create custom makeHostAndCert.js that respect `NGINX_PROXY_PATH`
venia docker-compose.yml example for connect to magento 2 as backend (need check if exists better way):
```yml
version: '2'
services:
  pwa:
    ...
  external_links:
    - "web.{{project_name}}:{{main_domain}}" #need implement auto genatation via `php builder.php`
    - "web.{{project_name}}:{{second_domain}}"
    ...
    - "web.{{project_name}}:{{last_domain}}"
  networks:
    - default
    - nginx-proxy

# used external proxy for use local magento 2 as backend
networks:
    nginx-proxy:
        external: true
```
```yml
version: '3.9'
services:
  pwa:
    ...
    [WIP]??? Need test configuration
  networks:
    - default
    - nginx-proxy

# used external proxy for use local magento 2 as backend
networks:
    nginx-proxy:
        external: true
```



### TODO
- [x] Add additional service configuration (redis, rabbitmq)
- [x] Implement https functional for single store. 
- [x] Implement https functional for multi-stores. 
- [ ] Implement configuration for Magento PWA.
- [x] Implement configuration for Magento Coding Standard.
- [x] Implement internal elasticsearch/opensearch service.
- [x] ~~Implement bash scripts for auto-generate ssl certificate~~
- [x] Implement auto-generate ssl certificate (`mkcert` from [docker-magento2-shared-infra][docker-magento2-shared-infra])
- [x] Implement bash scripts for set/update default services configs to env.php
- [ ] Implement LiveReload
- [ ] Implement dynamic varnish configs during build docker infrastructure (like it was done for php containers)
- [x] Implement dynamic internal elasticsearch/opensearch configs during build docker infrastructure (like it was done for php containers)
- [x] Implement dynamic redis configs during build docker infrastructure (like it was done for php containers)
- [x] Implement ngrok support via [magento ngrok extension][magento-ngrok]
- [x] Replace the custom template engine via [Twig templae engine][twig]
- [x] Implement [SPX][spx] - A simple profiler for PHP

[meanbee-docker-magento2]: https://github.com/meanbee/docker-magento2
[docker-magento2-shared-infra]: https://github.com/AndriynomeD/docker-magento2-shared-infra
[magento-coding-standard]: https://github.com/magento/magento-coding-standard
[magento-system-requirements]: https://devdocs.magento.com/guides/v2.4/install-gde/system-requirements.html
[magento-ngrok]: https://github.com/AndriynomeD/magento-ngrok
[twig]: https://twig.symfony.com/
[spx]: https://github.com/NoiseByNorthwest/php-spx
