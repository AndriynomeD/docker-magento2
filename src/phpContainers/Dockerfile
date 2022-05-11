#
#--------------------------------------------------------------------------
# {{generated_by_builder}}
#--------------------------------------------------------------------------

FROM <?php printf("php:%s-%s\n", $phpVersion, $flavour) ?>

MAINTAINER AndriynomeD <andriynomed.work@gmail.com>

###########################################################################
# Install dependencies
###########################################################################
<?php
    $imagePackages = array_merge([
        'libfreetype6-dev',
        'libicu-dev',
        'libjpeg62-turbo-dev',
        'libmcrypt-dev',
        'libpng-dev',
        'libxslt1-dev',
        'libzip-dev',
        'zip',
        'unzip',
        'sudo',
        'git'
    ], $packages ?? [], $flavourPackages ?? [])
?>

RUN apt-get update \
  && apt-get install -y \
    <?php echo join(" \\ \n    ", $imagePackages) ?>


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

RUN docker-php-ext-install \
  <?php echo join(" \\ \n  ", $imagePhpExtensions) ?>


# added zip to container (so there is no composer warning)
RUN if [ $(php -r "echo PHP_MAJOR_VERSION;") = "8" ] || { [ $(php -r "echo PHP_MAJOR_VERSION;") = "7" ] && { [ $(php -r "echo PHP_MINOR_VERSION;") = "4" ] || [ $(php -r "echo PHP_MINOR_VERSION;") = "3" ] ;} ;}; then \
    docker-php-ext-configure zip; \
else \
    docker-php-ext-configure zip --with-libzip; \
fi && \
  # Install the zip extension
  docker-php-ext-install zip && \
  php -m | grep -q 'zip'

<?php include "Docker-specific-packages"; ?>
###########################################################################
# Install Xdebug (but don't enable)
###########################################################################

<?php $xdebugVersion = ($xdebugVersion == 'latest' || $xdebugVersion == '') ? '' : '-' . $xdebugVersion; ?>
RUN pecl install -o -f <?php printf("xdebug%s\n", $xdebugVersion) ?>
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
    libsasl2-modules
COPY etc/postfix/config.cf /etc/postfix/
RUN sed -i "s/relayhost =[ ]*$//g" /etc/postfix/main.cf
RUN cat /etc/postfix/config.cf >> /etc/postfix/main.cf

###########################################################################
# Configuring...
###########################################################################

ENV PHP_MEMORY_LIMIT 2G
ENV PHP_ENABLE_XDEBUG false
ENV MAGENTO_ROOT /var/www/magento

ENV DEBUG false
ENV UPDATE_UID_GID false

ADD docker-entrypoint.sh /docker-entrypoint.sh

RUN ["chmod", "+x", "/docker-entrypoint.sh"]

ENTRYPOINT ["/docker-entrypoint.sh"]
