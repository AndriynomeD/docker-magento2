# Magento 2 Docker

A collection of Docker images for running Magento 2 through nginx and on the command line.

### Origin Repository

This repo is fork of [meanbee/docker-magento2][origin-repo] so you need read origin md file

Also this docker-compose services required [nginx-proxy][nginx-proxy]

For Max OS X useful: https://www.meanbee.com/developers/magento2-development-procedure.html

Also [install/delete/reinstall docker/docker-compose][https://gist.github.com/AndriynomeD/0d61773efef2408b3785f2f91aceae12]

### Usage

Install [nginx-proxy][nginx-proxy]

Make directory `magento` & `/persistent/mysql/mariadb10` inside root directory

Fill `composer.env` with you data

In `docker-compose.yml` file replace string `magento2.docker` with you're {{site_domain}} (example: magento230.site)

Add you're {{site_domain}} to `/etc/hosts` file:
```
127.0.0.1 {{site_domain}}
```

To run it:

    $ docker-compose up
    
### Problem

If you can't edit magento file in Phpstorm try it:

    $ sudo chmod -R g+w magento

Fix problem with owner (P.S. In you're system user `9933` can be another):

    $ sudo chown -R 9933:www-data var/cache

Also:
1. mageconfigsync diff function not work (but load/save work)
2. docker-compose run cli magerun2 list - no working (Incompatibility with Magento 2.3.0)
3. Email sending not workin for me so I disabled it by default

### Maybe useful

1. For debug inside container:


    $ sudo apt-get install nano
    
    $ sudo apt-get install telnet
    
    $ sudo apt-get install dnsutils
    
    $ sudo apt-get install iputils-ping

2. Mysql:
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



[ico-travis]: https://img.shields.io/travis/meanbee/docker-magento2.svg?style=flat-square
[ico-dockerbuild]: https://img.shields.io/docker/build/meanbee/magento2-php.svg?style=flat-square
[ico-downloads]: https://img.shields.io/docker/pulls/meanbee/magento2-php.svg?style=flat-square
[ico-dockerstars]: https://img.shields.io/docker/stars/meanbee/magento2-php.svg?style=flat-square

[link-travis]: https://travis-ci.org/meanbee/docker-magento2
[link-dockerhub]: https://hub.docker.com/r/meanbee/magento2-php
[origin-repo]: https://github.com/meanbee/docker-magento2
[nginx-proxy]: https://github.com/AndriynomeD/nginx-proxy