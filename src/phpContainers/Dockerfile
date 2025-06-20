#
#--------------------------------------------------------------------------
# {{generated_by_builder}}
#--------------------------------------------------------------------------

FROM <?php printf("php:%s-%s\n", $phpVersion, $flavour) ?>

LABEL maintainer="AndriynomeD <andriynomed.work@gmail.com>"

<?php
 $parts = explode('.', $phpVersion);
 $shortVersion = $parts[0] . '.' . $parts[1];
?>
ARG PHP_VERSION=<?= $shortVersion . PHP_EOL; ?>
###########################################################################
# Install dependencies
###########################################################################
<?php
    $imagePackages = array_merge([
        'libicu-dev',
        'libfreetype6-dev',
        'libjpeg62-turbo-dev',
        'libpng-dev',
        'libmcrypt-dev',
        'libxslt1-dev',
        'libzip-dev',
        'sudo',
        'git'
    ], $packages ?? [], $flavourPackages ?? [])
?>

RUN apt-get update \
  && apt-get install -y \
    <?= join(" \\ \n    ", $imagePackages) ?> \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

###########################################################################
# Install required PHP extensions
###########################################################################
<?php $imagePhpExtensions = array_merge([
  'dom',
  'intl',
  'mbstring',
  'pdo_mysql',
  'xsl',
  'soap',
  'bcmath',
  'pcntl',
  'sockets'
], $phpExtensions ?? [], $flavourPhpExtensions ?? []);
?>

RUN docker-php-ext-install -j$(nproc) \
  <?= join(" \\ \n  ", $imagePhpExtensions) ?> \
    && docker-php-source delete

###########################################################################
# Zip/Unzip (copy from https://github.com/laradock/laradock/blob/master/php-fpm/Dockerfile):
###########################################################################

RUN apt-get update \
  && apt-get install -y \
    libzip-dev zip unzip && \
    if [ ${PHP_VERSION} = "7.3" ] || [ ${PHP_VERSION} = "7.4" ] || [ $(php -r "echo PHP_MAJOR_VERSION;") = "8" ]; then \
      docker-php-ext-configure zip; \
    else \
      docker-php-ext-configure zip --with-libzip; \
    fi && \
    # Install the zip extension
    docker-php-ext-install zip && \
    php -m | grep -q 'zip' \
    && docker-php-source delete \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

<?php include "Dockerfile-specific-packages"; ?>
###########################################################################
# Install Xdebug (but don't enable)
###########################################################################

<?php $xdebugVersion = ($xdebugVersion == 'latest' || $xdebugVersion == '') ? '' : '-' . $xdebugVersion; ?>
RUN pecl install -o -f <?php printf("xdebug%s", $xdebugVersion) ?> && rm -rf /tmp/pear
ADD etc/php-xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug-settings.ini

###########################################################################
# Install Postfix
###########################################################################

# ADD etc/mail.ini /usr/local/etc/php/conf.d/zz-mail.ini
RUN apt-get update \
  && echo "postfix postfix/mailname string dockerized.site" | debconf-set-selections \
  && echo "postfix postfix/main_mailer_type string 'Internet Site'" | debconf-set-selections \
  && apt-get install -y \
    postfix \
    libsasl2-modules \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*
COPY etc/postfix/config.cf /etc/postfix/
RUN chmod u=rw,g=r,o=r /etc/postfix/config.cf \
    && sed -i "s/relayhost =[ ]*$//g" /etc/postfix/main.cf \
    && cat /etc/postfix/config.cf >> /etc/postfix/main.cf

###########################################################################
# Configuring...
###########################################################################

ENV PHP_MEMORY_LIMIT=2G \
    PHP_ENABLE_XDEBUG=false \
    MAGENTO_ROOT=/var/www/magento \
    DEBUG=false \
    UPDATE_UID_GID=false

COPY docker-entrypoint.sh /docker-entrypoint.sh
RUN ["chmod", "+x", "/docker-entrypoint.sh"]

ENTRYPOINT ["/docker-entrypoint.sh"]
