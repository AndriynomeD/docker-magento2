#
# {{generated_by_builder}}
#
#version: "3.9"
services:
<?php
//###########################################################################
//# Varnish
//###########################################################################
?>
<?php if (isset($DOCKER_SERVICES['varnish']) && $DOCKER_SERVICES['varnish']): ?>
  varnish:
    hostname: varnish.<?= $M2_PROJECT . PHP_EOL ?>
    image: meanbee/magento2-varnish:latest
    environment:
      - VIRTUAL_HOST=<?= $M2_VIRTUAL_HOSTS . PHP_EOL ?>
      - VIRTUAL_PORT=80
      - HTTPS_METHOD=noredirect
<?php
    /**
     * Multi-domains certificates
     * Specify multiple hosts with a comma delimiter to create multi-domains (SAN) certificates
     * (the first domain in the list will be the base domain).
     */
?>
      - CERT_NAME=<?= trim(explode(',', $M2_VIRTUAL_HOSTS)[0]) . PHP_EOL ?>
    ports:
      - 80
    depends_on:
      web:
        condition: service_started #service_healthy
    networks:
      default:
      nginx-proxy:

<?php endif; ?>
<?php
//###########################################################################
//# Nginx
//###########################################################################
?>
  web:
    hostname: web.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/nginx/
    ports:
      - 80
    depends_on:
      fpm:
       condition: service_started #service_healthy
    volumes:
      - <?= $M2_SOURCE_VOLUME; ?>:/var/www/magento
    env_file:
      - ./envs/global.env
<?php if (!(isset($DOCKER_SERVICES['varnish']) && $DOCKER_SERVICES['varnish'])): ?>
    environment:
      - VIRTUAL_HOST=<?= $M2_VIRTUAL_HOSTS . PHP_EOL ?>
      - VIRTUAL_PORT=80
      - HTTPS_METHOD=noredirect
      - CERT_NAME=<?= trim(explode(',', $M2_VIRTUAL_HOSTS)[0]) . PHP_EOL ?>
<?php endif; ?>
    networks:
      default:
      nginx-proxy:

<?php
//###########################################################################
//# PHP-FPM
//###########################################################################
?>
  fpm:
    hostname: fpm.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/php/<?= $PHP_VERSION ?>-fpm/
    ports:
      - 9000
    depends_on:
      db:
        condition: service_healthy
    volumes:
      - <?= $M2_SOURCE_VOLUME; ?>:/var/www/magento
    env_file:
      - ./envs/global.env
    networks:
      default:
      mail-services:
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'external'): ?>
      search-engine-net:
<?php endif; ?>

<?php
//###########################################################################
//# DATABASE
//###########################################################################
?>
  db:
    hostname: db.<?= $M2_PROJECT . PHP_EOL ?>
    image: <?= $DOCKER_SERVICES['database']['IMAGE'] . PHP_EOL ?>
    ports:
      - 3306
    volumes:
      - ./mysql_volumes/<?= $DOCKER_SERVICES['database']['VOLUME'] ?>:/var/lib/mysql
    environment:
      - MYSQL_ROOT_PASSWORD=root
      - MYSQL_DATABASE=<?= $M2_DB_NAME . PHP_EOL ?>
      - MYSQL_USER=magento2
      - MYSQL_PASSWORD=magento2
      - TERM=meh
    healthcheck:
<?php if ($DOCKER_SERVICES['database']['TYPE'] == 'mariadb' && version_compare($DOCKER_SERVICES['database']['VERSION'], '11.0', '>=')): ?>
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
<?php elseif ($DOCKER_SERVICES['database']['TYPE'] == 'mariadb'): ?>
      test: 'mysqladmin ping -h localhost -umagento2 -pmagento2'
<?php elseif ($DOCKER_SERVICES['database']['TYPE'] == 'mysql' || $DOCKER_SERVICES['database']['TYPE'] == 'percona'): ?>
      test: 'mysqladmin ping -h localhost -umagento2 -pmagento2'
<?php endif; ?>
      interval: 30s
      timeout: 30s
      retries: 3
      start_period: 10s
    networks:
      default:
      databases:

<?php
//###########################################################################
//# PHP-CLI
//###########################################################################
?>
  cli:
    hostname: cli.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/php/<?= $PHP_VERSION ?>-cli/
    depends_on:
      db:
        condition: service_healthy
<?php if (isset($DOCKER_SERVICES['redis']) && $DOCKER_SERVICES['redis']): ?>
      redis:
        condition: service_started #service_healthy
<?php endif; ?>
<?php if (isset($DOCKER_SERVICES['rabbitmq']) && $DOCKER_SERVICES['rabbitmq']): ?>
      rabbitmq:
        condition: service_started #service_healthy
<?php endif; ?>
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'internal'): ?>
<?php if ($DOCKER_SERVICES['search_engine']['TYPE'] == 'elasticsearch'): ?>
      elasticsearch:
        condition: service_started #service_healthy
<?php endif; ?>
<?php if ($DOCKER_SERVICES['search_engine']['TYPE'] == 'opensearch'): ?>
      opensearch:
        condition: service_started #service_healthy
<?php endif; ?>
<?php endif; ?>
    volumes:
      - ~/.composer/cache:/root/.composer/cache
      - <?= $M2_SOURCE_VOLUME; ?>:/var/www/magento
    env_file:
      - ./envs/global.env
      - ./envs/composer.env
      - ./envs/m2_install.env
    environment:
      - M2_VERSION=<?= $M2_VERSION . PHP_EOL ?><?php /** for correct cron setup according to M2_VERSION */ ?>
      - M2_EDITION=<?= $M2_EDITION . PHP_EOL ?>
    networks:
      default:
      mail-services:
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'external'): ?>
      search-engine-net:
<?php endif; ?>

<?php
//###########################################################################
//# CRON
//###########################################################################
?>
<?php if (isset($DOCKER_SERVICES['cron']) && $DOCKER_SERVICES['cron']): ?>
  cron:
    hostname: cron.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/php/<?= $PHP_VERSION ?>-cli/
    depends_on:
      db:
        condition: service_healthy
<?php if (isset($DOCKER_SERVICES['redis']) && $DOCKER_SERVICES['redis']): ?>
      redis:
        condition: service_started #service_healthy
<?php endif; ?>
<?php if (isset($DOCKER_SERVICES['rabbitmq']) && $DOCKER_SERVICES['rabbitmq']): ?>
      rabbitmq:
        condition: service_started #service_healthy
<?php endif; ?>
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'internal'): ?>
<?php if ($DOCKER_SERVICES['search_engine']['TYPE'] == 'elasticsearch'): ?>
      elasticsearch:
        condition: service_started #service_healthy
<?php endif; ?>
<?php if ($DOCKER_SERVICES['search_engine']['TYPE'] == 'opensearch'): ?>
      opensearch:
        condition: service_started #service_healthy
<?php endif; ?>
<?php endif; ?>
    command: run-cron
    volumes:
      - <?= $M2_SOURCE_VOLUME; ?>:/var/www/magento
    env_file:
      - ./envs/global.env
    environment:
      M2SETUP_EDITION: <?= $M2_EDITION . PHP_EOL ?><?php /** for correct cron setup according to M2_VERSION */ ?>
      M2SETUP_VERSION: <?= $M2_VERSION . PHP_EOL ?>
    networks:
      default:
      mail-services:
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'external'): ?>
      search-engine-net:
<?php endif; ?>
<?php endif; ?>

<?php
//###########################################################################
//# Magento Coding Standard
//###########################################################################
?>
<?php if (isset($DOCKER_SERVICES['magento-coding-standard']) && $DOCKER_SERVICES['magento-coding-standard']): ?>
  mcs:
    hostname: mcs.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/php/<?= $PHP_VERSION ?>-mcs/
      dockerfile: Dockerfile-mcs
    volumes:
      - ~/.composer/cache:/root/.composer/cache
      - ./magento-coding-standard:/var/www/magento-coding-standard
    env_file:
      - ./envs/global.env
      - ./envs/composer.env
    networks:
      default:

<?php endif; ?>
<?php
//###########################################################################
//# Internal Elasticsearch
//###########################################################################
?>
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'internal'): ?>
<?php if ($DOCKER_SERVICES['search_engine']['TYPE'] == 'elasticsearch'): ?>
  elasticsearch:
    hostname: elasticsearch.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/search_engine/elasticsearch/
    logging:
      driver: none
    ports:
      - 9200
    volumes:
      - ./volumes/elasticsearch/data:/usr/share/elasticsearch/data
    environment:
      - discovery.type=single-node
      - cluster.name=docker-cluster
      - bootstrap.memory_lock=true
      - xpack.security.enabled=false
      - "ES_JAVA_OPTS=-Xms1012m -Xmx1012m"
    ulimits:
      memlock:
        soft: -1
        hard: -1
    healthcheck:
      test: [
        "CMD-SHELL",
        "curl -s -u 'admin:admin' -k http://localhost:9200/_cluster/health | grep -E -q '\"status\":\"(green|yellow)\"' || exit 1"
      ]
      interval: 10s
      timeout: 10s
      retries: 12

<?php elseif ($DOCKER_SERVICES['search_engine']['TYPE'] == 'opensearch'): ?>
  opensearch:
    hostname: opensearch.<?= $M2_PROJECT . PHP_EOL ?>
    build:
      context: containers/search_engine/opensearch/
#    logging:
#      driver: none # json-file|none|...
    ports:
      - 9200
    volumes:
      - ./volumes/opensearch2:/usr/share/opensearch/data
    environment:
      - discovery.type=single-node
      - cluster.name=docker-cluster
      - bootstrap.memory_lock=true
      - DISABLE_INSTALL_DEMO_CONFIG=true
      - DISABLE_SECURITY_PLUGIN=true
      - "ES_JAVA_OPTS=-Xms1012m -Xmx1012m -Dlog4j2.formatMsgNoLookups=true"
      - "OPENSEARCH_JAVA_OPTS=-Xms1012m -Xmx1012m -Dlog4j2.formatMsgNoLookups=true"
    ulimits:
      memlock:
        soft: -1
        hard: -1
    healthcheck:
      test: [
        "CMD-SHELL",
        "curl -s -u 'admin:admin' -k http://localhost:9200/_cluster/health | grep -E -q '\"status\":\"(green|yellow)\"' || exit 1"
      ]
      interval: 10s
      timeout: 10s
      retries: 12

<?php endif; ?>
<?php endif; ?>
<?php
//###########################################################################
//# Redis
//###########################################################################
?>
<?php if (isset($DOCKER_SERVICES['redis']) && $DOCKER_SERVICES['redis']): ?>
  redis:
    hostname: redis.<?= $M2_PROJECT . PHP_EOL ?>
    image: redis:7.2
    ports:
      - 6379
#    healthcheck:
#      test: 'redis-cli ping || exit 1'
#      interval: 30s
#      timeout: 30s
#      retries: 3

<?php endif; ?>
<?php
//###########################################################################
//# RabbitMQ
//###########################################################################
?>
<?php if (isset($DOCKER_SERVICES['rabbitmq']) && $DOCKER_SERVICES['rabbitmq']): ?>
  rabbitmq:
    hostname: rabbitmq.<?= $M2_PROJECT . PHP_EOL ?>
    image: rabbitmq:3.8.9-management
    ports:
      - 4369
      - 5671
      - 5672
      - 15672
      - 25672
    environment:
      - RABBITMQ_DEFAULT_USER=rabbitmq_user
      - RABBITMQ_DEFAULT_PASS=rabbitmq_pass
      - RABBITMQ_DEFAULT_VHOST=rabbitmq
#    healthcheck:
#      test: ["CMD", "rabbitmq-diagnostics", "ping"]
#      interval: 10s
#      timeout: 5s
#      retries: 5

<?php endif; ?>
networks:
  nginx-proxy:
    external: true
  databases:
    external: true
<?php if ($DOCKER_SERVICES['search_engine'] && $DOCKER_SERVICES['search_engine']['CONNECT_TYPE'] == 'external'): ?>
  search-engine-net:
    external: true
<?php endif; ?>
  mail-services:
    external: true
