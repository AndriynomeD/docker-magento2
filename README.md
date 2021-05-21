# Magento 2 Docker

A collection of Docker images for running Magento 2 through nginx and on the command line.

### Origin Repository

This repo is fork of [meanbee/docker-magento2][origin-repo] so you need read origin md file  
Also this docker-compose services required [nginx-proxy][nginx-proxy]  
For Max OS X useful: https://www.meanbee.com/developers/magento2-development-procedure.html  
Also [install/delete/reinstall docker/docker-compose](https://gist.github.com/AndriynomeD/0d61773efef2408b3785f2f91aceae12)

### Usage 

1) Install & Configure [nginx-proxy][nginx-proxy]

2) Make directory `magento` (`{{magento_root}}`) inside root directory `{{root_directory}}`.

3) Prepare all config files:
    1) Fill `composer.env`, `global.env` with you data. 
        Example of required field for `composer.env` file:
        ```
        COMPOSER_MAGENTO_USERNAME={{repo.magento.com_username}}
        COMPOSER_MAGENTO_PASSWORD={{repo.magento.com_password}}
        ``` 
        P.S. For existing project `{{repo.magento.com_username}}` & `{{repo.magento.com_password}}` for `composer.env` can be found inside `{{magento_root}}/auth.json`
    2) Create renamed copy of following files in your `{{root_directory}}`:
        1) config.json.sample to config.json.         
        2) Remove not needed block of php versions from `php-containers` section. `grunt` can be available only under cli.
        3) Update sections with you data (read 'Single-store', 'Multi-store', 'Grunt' sections first):
            ```
            "M2_PROJECT": {{project_name}}
            "M2_VIRTUAL_HOSTS": {{all_site_domain}} 
            "M2_DB_NAME": {{database_name}}
            "PHP_VERSION": - php version
            "M2_INSTALL_DEMO" - section need only for install magento from scratch
                "BASE_URL": "http://{{main_domain}}/"
                "SECURE_BASE_URL": "https://{{main_domain}}/"
                "ELASTICSEARCH_ENABLED" - can disbaled only for < 2.4.0 (magento 2.4.0+ used elastic by default)
                "ELASTICSEARCH_SETTINGS" - update index-prefix (can be {{project_name}} without TLD
                "ADMIN_EMAIL": {{real email}} # magento 2.4.0+ used 2FA by default
    
            {{project_name}} - example: someproject.site
            {{all_site_domain}} -  see `Single-store` or `Multi-store` section
            {{main_domain}} - main site domain. see `Single-store` or `Multi-store` section
            {{database_name}} - Use unique db name with pattern: `{{client}}_{{project-name}}_{{dump-date}}` (example: someclient_someproject_20190710)
            ```

4) Build php containers & `docker-compose.yml`:
    ```shell
        $ php builder.php
    ```
    In `examples` folder you can see example of generated `docker-compose.yml`.

5) Up containers:
    ```shell
        $ docker-compose up
    ```
    P.S. Instead of `php bin/magento` use `sudo -uwww-data php bin/magento`:
    NOTE: Please set `--rm` to remove a created container after run.

6) Clone existing repo with magento into this folder or run next command for install magento from scratch:
    ```shell
        $ docker-compose run --rm cli magento-installer
    ```

7) If you clone  existing repo import database:
    1) Copy database dump into `{{magento_root}}`.
    2) Go to cli-container & import database dump:
    ```shell
        $ docker-compose run --rm cli bash
        $ cd /var/www/magento/
    ```
    Import dump:
    ```shell
        $ mysql -hdb -umagento2 -p {{youre_database_name}} < {{youre_database_dump}}
    ```
    Or using source:
    ```shell
        $ mysql -hdb -umagento2 -p 
        $ use {{youre_database_name}};
        $ source {{youre_database_dump}}
    ```
    Copy into right places under `{{magento_root}}` all magento required secure sensitive file like `app/etc/env.php` file.  
    In `env.php` file use next config for database:
    ```
    'host' => 'db',
    'dbname' => {{database_name}},
    'username' => 'magento2',
    'password' => 'magento2',
    
    ```
    Maybe after you want create admin user:
    ```shell
        $ sudo -uwww-data php bin/magento admin:user:create --admin-user="admin" --admin-password="admin123" --admin-email="admin@example.com" --admin-firstname="AdminFirstName" --admin-lastname="AdminLastName"
    ```

### Single-store

1) `config.json` params:
    1) {{all_site_domain}} same as {{main_domain}} (example: someproject.site)
2) Add all you're site domain to `/etc/hosts` file:
    ```
    # single-store sites (docker):
    ...
    127.0.0.1 {{main_domain}}
    ...
    # end single-store sites (docker)
    ```
    Example:
    ```
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
    ```
    # multi-store {{unique project name or main_domain or unique number}} (docker):
    127.0.0.1 {{main_domain}} {{additional_domain_1}} ... {{additional_domain_N}}
    # end multi-store {{unique project name or number}} (docker)
    ```
    Example:
    ```
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
        $ docker-compose run --rm cli bash
        $ cd /var/www/magento/
        $ npm install
        $ npm update
    ```
    
    Then in bash of cli-container got to magento root directory and use standard grunt command:
    ```shell
        $ sudo -uwww-data grunt clean   Removes the theme related static files in the pub/static and var directories.
        $ sudo -uwww-data grunt exec    Republishes symlinks to the source files to the pub/static/frontend/ directory.
        $ sudo -uwww-data grunt less    Compiles .css files using the symlinks published in the pub/static/frontend/ directory.
        $ sudo -uwww-data grunt watch   Tracks the changes in the source files, recompiles .css files, and reloads the page in the browser.
    ```
    Example of run grunt watch:
    ```shell
        $ docker-compose run --rm cli bash
        $ cd /var/www/magento/
        $ sudo -uwww-data grunt clean && sudo -uwww-data grunt exec && sudo -uwww-data grunt less
        $ sudo -uwww-data grunt watch
    ```

Reloads the page in the browser not working.  
Also `Warning: Error compiling lib/web/css/docs/source/docs.less Use --force to continue.` - it's magento native bug. 

### PphStorm

1) #### PphStorm Magento plugin:
    1. Install & Enable official Magento plugin for PphStorm.
   
2) #### Xdebug config:

    1. `Add Configuration` or `Edit Configuration`
    2. Add `PHP remote debug`
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
    3. Apply this config
    
    For cli debug:
    ```shell
        sudo su -l www-data -s /bin/bash
        cd /var/www/magento/ && export XDEBUG_CONFIG="remote_host=host.docker.internal"
        php bin/magento setup:up    (command for debug example)
        exit
    ```
    

3) #### [Magento Coding Standard][magento-coding-standard]
    1. Install required packages:
        1. For Community/Commerce:
            ```shell
                composer require --dev magento/magento-coding-standard
            ```
       2. ~~For Commerce Cloud:~~ 
             ```shell
                composer require --dev magento/magento-coding-standard phpmd/phpmd:@stable squizlabs/php_codesniffer:~3.4.0 --sort-packages
             ```
    2. Due to security, when installed this way the Magento standard for phpcs cannot be added automatically. You can achieve this by adding the following to your project's `composer.json`:
        ```json
        "scripts": {
          "post-install-cmd": [
            "([ $COMPOSER_DEV_MODE -eq 0 ] || vendor/bin/phpcs --config-set installed_paths ../../magento/magento-coding-standard/)"
          ],
          "post-update-cmd": [
            "([ $COMPOSER_DEV_MODE -eq 0 ] || vendor/bin/phpcs --config-set installed_paths ../../magento/magento-coding-standard/)"
          ]
        }
        ```
    3. Config PhpStorm:
        ```shell
        Settings->Directories->Excluded files: *Test*
        P.S. not work with vendor/*
        ```
        ```shell
        Settings->Languages & Frameworks->PHP
            PHP Language level: 7.3
            CLI Interpreter: click '...' -> click '+' -> choose 'From Docker,...'
                Config Remote PHP Interpreter:
                choose 'Docker Compose'
                Service: 'cli'
            Path mapping: map {{magento_root}} in left column to path `/var/www/magento` inside container.
        ```
       ```shell
       Settings->Languages & Frameworks->PHP->Quality tools
           PHP_CodeSniffer:
           {{absolute_path}}/vendor/bin/phpcs (file should be a link to ../squizlabs/php_codesniffer/bin/phpcs)
           {{absolute_path}}/vendor/bin/phpcbf (file should be a link to ../squizlabs/php_codesniffer/bin/phpcbf)
           PHP Mess Detector:
           {{absolute_path}}/vendor/bin/phpmd (file should be a link to ../phpmd/phpmd/src/bin/phpmd)
       ```
        ```shell
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

### Maybe useful

1) For debug inside 'ubuntu' container (docker exec -it {{container}} bash):
    ```shell
        $ sudo apt-get install nano
        $ sudo apt-get install rsyslog
        $ sudo apt-get install telnet
        $ sudo apt-get install dnsutils
        $ sudo apt-get install iputils-ping
    ```
2) For debug inside 'alpine' container (docker exec -it {{container}} sh):
    ```shell
        $ yum install iputils
    ``` 
3) In Mysql:
    ```shell
        $ mysql -hdb -umagento2 -p
    ```
    ```
    show databases;
    ```
    ```
    create database {{youre_database_name}};
    ```
    ```
    GRANT ALL PRIVILEGES ON {{youre_database_name}}.* TO 'magento2'@'%'; - add user `magento2` grant for {{youre_database_name}}
    ```
    ```
    use {{youre_database_name}};
    ```
    ```
    source {{youre_database_dump}}
    ```
    Change password:
    ```
    UPDATE mysql.user SET Password=PASSWORD('root') WHERE User='root';
    FLUSH PRIVILEGES;
    ```

4) PphStorm & Database
    Get real db host & port (this info can be find using next command):
    ```shell
        $ docker ps -a
    ```

Next use it when connected to db by PphStorm

### Problem

If you can't edit magento file in Phpstorm try it:
```shell
    $ sudo usermod -aG www-data ${USER}
    $ sudo chmod -R g+w magento
```
Fix problem with owner:
```shell
    $ sudo chown -R www-data:www-data var/cache
```
Example of fix permission problem inside cli-container:
```shell
    $ cd /var/www/ && sudo chown -R www-data:www-data magento/ && sudo chmod -R g+w magento/ && cd /var/www/magento/ && rm -rf var/cache && rm -rf var/page_cache && rm -rf var/generation && rm -rf var/session
```

### TODO
1. Implement https functional.
2. Implement configuration for Magento PWA.
3. Implement internal elasticsearch service.
4. Implement gulp for cli.
5. Implement bash scripts for generate ssl certificate, set default config to env.php

[ico-travis]: https://img.shields.io/travis/meanbee/docker-magento2.svg?style=flat-square
[ico-dockerbuild]: https://img.shields.io/docker/build/meanbee/magento2-php.svg?style=flat-square
[ico-downloads]: https://img.shields.io/docker/pulls/meanbee/magento2-php.svg?style=flat-square
[ico-dockerstars]: https://img.shields.io/docker/stars/meanbee/magento2-php.svg?style=flat-square

[link-travis]: https://travis-ci.org/meanbee/docker-magento2
[link-dockerhub]: https://hub.docker.com/r/meanbee/magento2-php
[origin-repo]: https://github.com/meanbee/docker-magento2
[nginx-proxy]: https://github.com/AndriynomeD/nginx-proxy
[magento-coding-standard]: https://github.com/magento/magento-coding-standard