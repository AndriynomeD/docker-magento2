#
# {{generated_by_builder}}
#

FROM <?php printf("php:%s-%s\n", $version, $flavour) ?>

MAINTAINER Nick Jones <nick@nicksays.co.uk>
<?php
    $packages = array_merge([
        'libfreetype6-dev',
        'libicu-dev',
        'libjpeg62-turbo-dev',
        'libmcrypt-dev',
        'libpng-dev',
        'libxslt1-dev',
        'libzip-dev',
        'sudo'
    ], $flavourSpecificPackages ?? [], $imageSpecificPackages ?? [])
?>

# Install dependencies
RUN apt-get update \
  && apt-get install -y \
    <?php echo join(" \\ \n    ", $packages) ?>


# Configure the gd library
RUN docker-php-ext-configure \
<?php if (version_compare($version, '7.4', '>=')): ?>
  gd --prefix=/usr --with-freetype --with-jpeg
<?php else: ?>
  gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
<?php endif; ?>

# Install required PHP extensions
<?php $phpExtensions = array_merge([
  'dom',
  'gd',
  'intl',
  'mbstring',
  'pdo_mysql',
  'xsl',
  'zip',
  'soap',
  'bcmath',
  'pcntl',
  'sockets'
], $imageSpecificPhpExtensions ?? []);
?>

RUN docker-php-ext-install \
  <?php echo join(" \\ \n  ", $phpExtensions) ?>


# Install & Configure php calendar for use easter_days function
RUN docker-php-ext-install calendar && docker-php-ext-configure calendar

# Install Xdebug (but don't enable)
<?php $xdebugVersion = ($xdebugVersion == 'latest' || $xdebugVersion == '') ? '' : '-' . $xdebugVersion; ?>
RUN pecl install -o -f <?php printf("xdebug%s\n", $xdebugVersion) ?>

ENV PHP_MEMORY_LIMIT 2G
ENV PHP_ENABLE_XDEBUG false
ENV MAGENTO_ROOT /var/www/magento

ENV DEBUG false
ENV UPDATE_UID_GID false

ADD etc/php-xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug-settings.ini
ADD etc/mail.ini /usr/local/etc/php/conf.d/zz-mail.ini

# Install Postfix
RUN apt-get update \
  && echo "postfix postfix/mailname string dockerized.site" | debconf-set-selections \
  && echo "postfix postfix/main_mailer_type string 'Internet Site'" | debconf-set-selections \
  && apt-get install -y \
    postfix \
    libsasl2-modules
COPY etc/postfix/config.cf /etc/postfix/
RUN sed -i "s/relayhost =[ ]*$//g" /etc/postfix/main.cf
RUN cat /etc/postfix/config.cf >> /etc/postfix/main.cf

ADD docker-entrypoint.sh /docker-entrypoint.sh

RUN ["chmod", "+x", "/docker-entrypoint.sh"]

ENTRYPOINT ["/docker-entrypoint.sh"]
