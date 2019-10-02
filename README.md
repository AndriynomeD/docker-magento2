# Magento 2 Docker

A collection of Docker images for running Magento 2 through nginx and on the command line.

### Origin Repository

This repo is fork of [meanbee/docker-magento2][origin-repo] so you need read origin md file

Also this docker-compose services required [nginx-proxy][nginx-proxy]

For Max OS X useful: https://www.meanbee.com/developers/magento2-development-procedure.html

Also [install/delete/reinstall docker/docker-compose](https://gist.github.com/AndriynomeD/0d61773efef2408b3785f2f91aceae12)

### Usage

1) Install [nginx-proxy][nginx-proxy]

2) Make directory `magento` (`{{magento_root}}`) inside root directory `{{root_directory}}`. If need clone existing repo with magento into this folder.

3) Prepare all config files:
    1) Fill `composer.env`, `global.env` with you data. 
        Example of required field for `composer.env` file:
        ```
        COMPOSER_MAGENTO_USERNAME={{repo.magento.com_username}}
        COMPOSER_MAGENTO_PASSWORD={{repo.magento.com_password}}
        ``` 
        P.S. For existing project `{{repo.magento.com_username}}` & `{{repo.magento.com_password}}` for `composer.env` can be found inside `{{magento_root}}/auth.json`
    2) Create renamed copy of following files in your Magento `{{root_directory}}`:
        1) config.json.sample to config.json. Remove not needed block of php versions.
        2) docker-compose-config.json.sample to docker-compose-config.json. Fill `docker-compose-config.json` with you data. If `varnish`/`cron` not needed set it to false.
            `docker-compose-config.json` params:
            ```
            "M2SETUP_PROJECT": {{project_name}}
            "M2SETUP_VIRTUAL_HOST": {{all_site_domain}} 
            "M2SETUP_BASE_URL": "http://{{main_domain}}/"
            "M2SETUP_SECURE_BASE_URL": "https://{{main_domain}}/"
            "M2SETUP_DB_NAME": {{database_name}}
            "M2SETUP_PHP": "7.2"
    
            {{project_name}} - example: someproject.site
            {{all_site_domain}} -  see `Single-store` or `Multi-store` section
            {{main_domain}} - main site domain. see `Single-store` or `Multi-store` section
            {{database_name}} - Use unique db name with pattern: `{{client}}_{{project-name}}_{{dump-date}}` (example: someclient_someproject_20190710)
            ```

4) Build php containers & `docker-compose.yml`:
    ```shell
        $ php builder.php
        $ php docker-compose-builder.php
    ```
    In `examples` folder you can see example of generated `docker-compose.yml`.

5) Up containers:
    ```shell
        $ docker-compose up
    ```
    
    P.S. Instead of `php bin/magento` use `magento-command`:
    ```shell
        $ docker-compose run --rm cli magento-command deploy:mode:show 
    ```
    NOTE: Please set `--rm` to remove a created container after run.
    
    Or inside container run `php bin/magento` from user `www-data` (for example see `src/bin/magento-command`)

6) Import database:
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

1) `docker-compose-config.json` params:
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
    127.0.0.1 someanotherproject1.site
    127.0.0.1 someanotherproject2.site
    127.0.0.1 someproject.site
    127.0.0.1 someanotherproject3.site
    # end single-store sites (docker)
    ```

### Multi-store
Example: we have 3 store/website: someproject.site, someproject-vip.site, someproject-retail.site
1) `docker-compose-config.json` params:
    1) {{all_site_domain}} - comma separated all site domains (example: `someproject.site,someproject-vip.site,someproject-retail.site`)
    2) {{main_domain}} - So you should choose one domain main (example: we choose `someproject.site` like a {{main_domain}})
2) In folder `{{root_directory}}/nginx/etc/multi_vhost/` create one/multiple own config file(s) for multi-store. Use file `example_vhost.conf` as example.
3) Add all you're site domain to `/etc/hosts` file:
    ```
    # multi-store {{unique project name or main_domain or unique number}} (docker):
    127.0.0.1 {{main_domain}}
    127.0.0.1 {{additional_domain_1}}
    ...
    127.0.0.1 {{additional_domain_N}}
    # end multi-store {{unique project name or number}} (docker)
    ```
    Example:
    ```
    # multi-store 9 (docker)
    127.0.0.1 someproject.site
    127.0.0.1 someproject-vip.site
    127.0.0.1 someproject-retail.site
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

1) Xdebug config:
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
                map magento folder in left column to path `/var/www/magento` inside container
        ```shell
    3. Apply this config
2) URN config:
   1. Copy `{{root_directory}}/.idea/misc.xml` file to `{{magento_root}}/.idea/misc.xml`.
   2. Go to cli-container & generate urn:
       ```shell
           $ docker-compose run --rm cli bash
           $ cd /var/www/magento/
           $ sudo -uwww-data php bin/magento dev:urn-catalog:generate .idea/misc.xml
       ```
   3. Move file `{{magento_root}}/.idea/misc.xml` to `{{root_directory}}/.idea/misc.xml`.
   4. Replace in file `{{magento_root}}/.idea/misc.xml` string `/var/www` by `$PROJECT_DIR$`.


### Maybe useful

1) For debug inside 'ubuntu' container (docker exec -it {{container}} bash):
    ```shell
        $ sudo apt-get install nano
        $ sudo apt-get install rsyslog
        $ sudo apt-get install telnet
        $ sudo apt-get install dnsutils
        $ sudo apt-get install iputils-ping
    ```
2_ For debug inside 'alpine' container (docker exec -it {{container}} sh):
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
    $ sudo usermod -aG www-data {{user}}
    $ sudo chmod -R g+w magento
```
Fix problem with owner (P.S. In you're system user `9933` can be another):
```shell
    $ sudo chown -R 9933:www-data var/cache
```
Example of fix permission problem inside cli-container:
```shell
    $ cd /var/www/ && sudo chown -R 9933:www-data magento/ && sudo chmod -R g+w magento/ && cd /var/www/magento/ && rm -rf var/cache && rm -rf var/page_cache && rm -rf var/generation && rm -rf var/session
```
Also:
1. mageconfigsync diff function not work (but load/save work)
2. docker-compose run --rm cli magerun2 list - not working (Incompatibility with Magento 2.3.0)


[ico-travis]: https://img.shields.io/travis/meanbee/docker-magento2.svg?style=flat-square
[ico-dockerbuild]: https://img.shields.io/docker/build/meanbee/magento2-php.svg?style=flat-square
[ico-downloads]: https://img.shields.io/docker/pulls/meanbee/magento2-php.svg?style=flat-square
[ico-dockerstars]: https://img.shields.io/docker/stars/meanbee/magento2-php.svg?style=flat-square

[link-travis]: https://travis-ci.org/meanbee/docker-magento2
[link-dockerhub]: https://hub.docker.com/r/meanbee/magento2-php
[origin-repo]: https://github.com/meanbee/docker-magento2
[nginx-proxy]: https://github.com/AndriynomeD/nginx-proxy