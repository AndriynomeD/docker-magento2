# Magento 2 Docker

A collection of Docker images for running Magento 2 through nginx and on the command line.

### Origin Repository

This repo is fork of [meanbee/docker-magento2][origin-repo] so you need read origin md file  
Also this docker-compose services required [nginx-proxy][nginx-proxy]  
For Max OS X useful: https://www.meanbee.com/developers/magento2-development-procedure.html  
Also [install/delete/reinstall docker/docker-compose](https://gist.github.com/AndriynomeD/0d61773efef2408b3785f2f91aceae12)

### Usage 

1) Install & Configure [nginx-proxy][nginx-proxy]

2) Make directory `magento` (`{{magento_root}}`) inside project root directory `{{root_directory}}`.

3) Prepare all config files:
    1) Copy `config.json.sample` to `config.json`, `global.env.sample`, `global.env` & fill it with you data. 
        Example of required field for `composer.env` file:
        ```env
        COMPOSER_MAGENTO_USERNAME={{repo.magento.com_username}}
        COMPOSER_MAGENTO_PASSWORD={{repo.magento.com_password}}
        ``` 
        P.S. For existing project `{{repo.magento.com_username}}` & `{{repo.magento.com_password}}` for `composer.env` can be found inside `{{magento_root}}/auth.json`
    2) Create renamed copy of following files in your `{{root_directory}}`:
        1) Copy `config.json.sample` to `config.json`.
        2) Update sections with you data (read 'Single-store', 'Multi-store', 'Grunt' sections first), also check [magento 2 system requirements][magento-system-requirements]:
            ```json
            "M2_PROJECT": {{project_name}}
            "M2_VIRTUAL_HOSTS": {{all_site_domain}} 
            "M2_DB_NAME": {{database_name}}
            "PHP_VERSION": - php version
            "M2_INSTALL" - section need only for install magento from scratch or install clean db
                "BASE_URL": "http://{{main_domain}}/"
                "SECURE_BASE_URL": "https://{{main_domain}}/"
                "ADMIN_EMAIL": {{real email}} # magento 2.4.0+ used 2FA by default
                "EDITION": community/enterprise
            "M2_SETTINGS" - section with magento additional services settings (also used by magento-instaler scripts)
                "ELASTICSEARCH_SETTINGS" - update index-prefix (can be {{project_name}} without TLD
            "DOCKER_SERVICES": additional services, such as varnish, cron. If you want to use magento as Venia backend set `venia` to `true`.
            "HTTPS_HOST": if set `true` will generate self-signed ssl sertificate in nginx-proxy folder(required `NGINX_PROXY_PATH`). P.S. Not tested with Multi-stores
            Now `"composerVersion": "latest"` will set version 2.X for m2.4.2+ & 1.X in another case
           
            {{project_name}} - example: someproject.site
            {{all_site_domain}} -  see `Single-store` or `Multi-store` section
            {{main_domain}} - main site domain. see `Single-store` or `Multi-store` section
            {{database_name}} - Use unique db name with pattern: `{{client}}_{{project-name}}_{{dump-date}}` (example: someclient_someproject_20190710)
            ```
            `grunt` can be available only under cli.


4) Build php containers & `docker-compose.yml`:
    ```shell
        php builder.php
    ```
    In `examples` folder you can see example of generated `docker-compose.yml`.

5) Up containers (first time up better don't add key `-d` for check if all okay):
    ```shell
        docker-compose up -d
    ```

6) Clone existing repo with magento into this folder or run next command for install magento from scratch:
    ```shell
        docker-compose run --rm cli magento-installer
    ```
7) Now you can enter to cli (you should run all magento command under cli).
   P.S. Instead of `php bin/magento` use `sudo -uwww-data php bin/magento`:
   NOTE: Please set `--rm` to remove a created container after run.
   Example :
    ```shell
        docker-compose run --rm cli bash
    ```
   
8) If you clone  existing repo import database:
    1) Copy database dump into `{{magento_root}}`.
    2) Go to cli-container & import database dump:
    ```shell
        docker-compose run --rm cli bash
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
    In `env.php` file use next config for database:
    ```php
    'host' => 'db',
    'dbname' => {{database_name}},
    'username' => 'magento2',
    'password' => 'magento2',
    ```
    Maybe after you want create admin user:
    ```shell
        sudo -uwww-data php bin/magento admin:user:create --admin-user="admin" --admin-password="admin123" --admin-email="admin@example.com" --admin-firstname="AdminFirstName" --admin-lastname="AdminLastName"
    ```
    3) Update env.php with service configs (also need to do after updating additional docker services)
    ```shell
       magento-service-updater
    ```

### Single-store

1) `config.json` params:
    1) {{all_site_domain}} same as {{main_domain}} (example: someproject.site)
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
    1) {{all_site_domain}} - comma separated all site domains (example: `someproject.site,someproject-vip.site,someproject-retail.site`)
    2) {{main_domain}} - So you should choose one domain main (example: we choose `someproject.site` like a {{main_domain}})
2) In folder `{{root_directory}}/nginx/etc/multi_vhost/` create one/multiple own config file(s) for multi-store. Use file `example_vhost.conf` as example.
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
3. {{magento_root}}/dev/tools/grunt/configs/themes.js to {{magento_root}}/dev/tools/grunt/configs/local-themes.js
4. Update local-themes.js by include your local site theme
5. Inside `{{root_directory}}`: 
    ```shell
        docker-compose run --rm cli bash
        npm install
        npm update
    ```
    
    Then in bash of cli-container got to magento root directory and use standard grunt command:
    ```shell
        sudo -uwww-data grunt clean   Removes the theme related static files in the pub/static and var directories.
        sudo -uwww-data grunt exec    Republishes symlinks to the source files to the pub/static/frontend/ directory.
        sudo -uwww-data grunt less    Compiles .css files using the symlinks published in the pub/static/frontend/ directory.
        sudo -uwww-data grunt watch   Tracks the changes in the source files, recompiles .css files, and reloads the page in the browser.
    ```
    Example of run grunt watch:
    ```shell
        docker-compose run --rm cli bash
        sudo -uwww-data grunt exec:all && sudo -uwww-data grunt less
        sudo -uwww-data grunt watch
    ```

Reloads the page in the browser not working.  
Also `Warning: Error compiling lib/web/css/docs/source/docs.less Use --force to continue.` - it's magento native bug. 

### PphStorm

1) #### PphStorm Magento plugin:
    1. Install & Enable official Magento plugin for PphStorm.
    2. Enabled plugin for project in Settings->PHP->Frameworks->Magento
    3. Config Project PHP interpreter: 
    ```shell
    Settings->Directories->Excluded files: *Test*
    P.S. not work with vendor/*
    ```
    ```shell
    Settings->PHP
        PHP Language level: `PHP_VERSION`
        CLI Interpreter: click '...' -> click '+' -> choose 'From Docker,...'
            Config Remote PHP Interpreter:
                choose 'Docker Compose'
                Name: 'cli'
                Service: 'cli'
        Path mapping: map {{magento_root}} in left column to path `/var/www/magento` inside container.
    ```
   
2) #### Xdebug config:
    
    1. Enabled Xdebug in global.env file (PHP_ENABLE_XDEBUG=true).
    2. `Add Configuration` or `Edit Configuration`
    3. Add `PHP remote debug`
        ```shell
        Name: Configuration
        IDE key(s): PHPSTORM
        Server: 
            Name: Configuration
            host: localhost
            port: 80
            Debuger: Xdebug
            Use path mapping: Yes
                map {{magento_root}} in left column to path `/var/www/magento` inside container
        ```
    4. Apply this config
    
    For cli debug:
    ```shell
        sudo su -l www-data -s /bin/bash
        cd /var/www/magento/ && export XDEBUG_CONFIG="remote_host=host.docker.internal"
        php bin/magento setup:up    (command for debug example)
        exit
    ```
   ```shell
        sudo su -l www-data -s /bin/bash
        cd /var/www/magento/ && export XDEBUG_CONFIG="client_host=host.docker.internal"
        php bin/magento setup:up    (command for debug example)
        exit
    ```
    

3) #### [Magento Coding Standard][magento-coding-standard]
    1. On "Prepare all config files" step in `config.json` set `magento-coding-standard` under `DOCKER_SERVICES` to `true`.
    2. Currently, PhpStorm don't have docker connection for eslint so you need install npm on host machine.
    3. Install Magento Coding Standard project:
        ```shell
            docker-compose run --rm mcs magento-coding-standard-installer
            cd `{{root_directory}}/magento-coding-standard`
            npm init
        ```
    4. Config PhpStorm (after magento & magento-coding-standard projects was setup):
       ```plaintext
       Settings->Languages & Frameworks->PHP->Quality tools
           PHP_CodeSniffer:
           CLI Interpreter: click '...' -> click '+' -> choose 'From Docker,...'
                Config Remote PHP Interpreter:
                    choose 'Docker Compose'
                    Name: 'mcs'
                    Service: 'mcs'
           Path mapping: map <Project root>/magento-coding-standard->/var/www/magento-coding-standard.
           PHP_CodeSniffer path: `/var/www/magento-coding-standard/vendor/bin/phpcs`
           Path to phpcbf: `/var/www/magento-coding-standard/vendor/bin/phpcbf`
       
           PHP Mess Detector:
           CLI Interpreter: click '...' -> click '+' -> choose already created "cli" remote PHP Interpreter
           Path mapping: map <Project root>/magento->/var/www/magento
           PHP Mess Detector path: `/var/www/magento/vendor/bin/phpmd`
       ```
        ```plaintext
        Settings->Editor->Inspection
        PHP->Quality tools
            ->PHP Mess Detector validation:
                Choose all 'Options'
                Add custom ruleset:
                path: {{absolute_path}}/magento/dev/tests/static/testsuite/Magento/Test/Php/_files/phpmd/ruleset.xml'
            ->PHP_CodeSniffer validation: 
                Check files with extensions: 'php,js,css,inc,phtml'
                Show sniff name: Yes
                Coding Standard: Magento2 (if not see press reload & scroll up list)
        ```
        ```plaintext
       Settings->Languages & Frameworks
       JavaScript->Code Quality Tools->ESLint:
           Manual ESLint configuration:
               ESLint package: {{absolute_path}}/magento-coding-standard/node_modules/eslint
               Configuration file: {{absolute_path}}/magento-coding-standard/eslint/.eslintrc
               Additional rules directory: {{absolute_path}}/magento-coding-standard/eslint/rules
       ```
   P.S. It enough create mcs container per different php version. If you already have mcs for current php version in another project you can create connect to this mcs container.

   For upgrade magento coding standard enter inside mcs container:
   ```shell
      docker-compose run --rm mcs bash
    ```

### Ngrok support (usefully for testing online payment methods etc.)
1) Install ngrok. Copy `ngrok` to `/usr/local/bin` for run ngrok from any folder by command 'ngrok'
2) Download & install [magento-ngrok extension](https://github.com/shkoliar/magento-ngrok) to app/code/Shkoliar/Ngrok folder.
3) Run to cli container and set `sudo -uwww-data php bin/magento config:set --lock-env web/url/redirect_to_base 0`.
4) Redeploy project: set:up, s:d:c, etc.
5) Run ngrok with additional param host-header. Example `ngrok http -host-header=magento243.site 80`:
    ```shell
        ngrok http -host-header={{local_site_domain}} 80
    ```
    How it works: internet->ngrok->reverse-proxy->project-entrypoint(nginx/varnish->nginx->varnish)->reverse-proxy->ngrok->internet

    P.S. It for local development - do not commit Shkoliar_Ngrok into project git.

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

If you can't edit magento file in Phpstorm try it:
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

### Venia Support (WIP)
- [x] Implement https functional.
- [x] Implement Venia Sample Data.
- [ ] Implement configuration for Magento PWA.
Because Venia required worked connection to magento 2 think at least as ext step is create separate docker-compose.yml in `{{root_directory}}/magento_venia` directory. PWA project should be inited in `{{root_directory}}/magento_venia/source`. In Venia `DEV_SERVER_HOST`==`{{main_domain}}`, `DEV_SERVER_PORT`==`443`.
Also need create custom makeHostAndCert.js that respect `NGINX_PROXY_PATH`
venia docker-compose.yml example for connect to magento 2 as backend (need check if exists better way):
```yml
services:
  pwa:
    ...
  external_links:
    - "web.{{project_name}}:{{main_domain}}" #need mplement auto genatation via `php builder.php`
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



### TODO
- [x] Add additional service configuration (redis, rabbitmq)
- [x] Implement https functional for single store. 
- [ ] Implement https functional for multi-stores. 
- [ ] Implement configuration for Magento PWA.
- [x] Implement configuration for Magento Coding Standard.
- [x] Implement internal elasticsearch service.
- [ ] Implement gulp for cli.
- [x] Implement bash scripts for generate ssl certificate
- [x] Implement bash scripts for set/update default services configs to env.php
- [ ] Implement LiveReload
- [ ] Implement dynamic varnish configs during build.php run (like it was done for php containers)
- [ ] Implement dynamic internal elasticsearch configs during build.php run (like it was done for php containers)
- [ ] Implement dynamic redis configs during build.php run (like it was done for php containers)

[ico-travis]: https://img.shields.io/travis/meanbee/docker-magento2.svg?style=flat-square
[ico-dockerbuild]: https://img.shields.io/docker/build/meanbee/magento2-php.svg?style=flat-square
[ico-downloads]: https://img.shields.io/docker/pulls/meanbee/magento2-php.svg?style=flat-square
[ico-dockerstars]: https://img.shields.io/docker/stars/meanbee/magento2-php.svg?style=flat-square

[link-travis]: https://travis-ci.org/meanbee/docker-magento2
[link-dockerhub]: https://hub.docker.com/r/meanbee/magento2-php
[origin-repo]: https://github.com/meanbee/docker-magento2
[nginx-proxy]: https://github.com/AndriynomeD/nginx-proxy
[magento-coding-standard]: https://github.com/magento/magento-coding-standard
[magento-system-requirements]: https://devdocs.magento.com/guides/v2.4/install-gde/system-requirements.html
