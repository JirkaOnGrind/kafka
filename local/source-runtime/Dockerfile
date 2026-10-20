FROM php:7.1-apache
# Historical runtime for an OFFLINE source clone only, never for production.
RUN printf 'deb http://archive.debian.org/debian buster main\n' > /etc/apt/sources.list \
 && apt-get -o Acquire::Check-Valid-Until=false update \
 && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libfreetype6-dev libmcrypt-dev libicu-dev libxml2-dev zlib1g-dev libcurl4-openssl-dev \
 && docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/ \
 && docker-php-ext-install -j2 pdo_mysql mysqli gd mbstring mcrypt intl soap zip curl \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*
COPY apache-source.conf /etc/apache2/conf-enabled/kafka-source.conf
COPY kafka-source-guard.php /usr/local/etc/php/kafka-source-guard.php
COPY preview-guard.ini /usr/local/etc/php/conf.d/kafka-preview-guard.ini
RUN printf 'sendmail_path=/bin/false\nallow_url_fopen=Off\nallow_url_include=Off\ndisplay_errors=Off\nlog_errors=On\n' > /usr/local/etc/php/conf.d/kafka-isolation.ini
