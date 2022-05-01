FROM php:8.1.5-fpm

WORKDIR /usr/local/www/data/app/

RUN apt-get -qq update \
    && usermod -u 1000 www-data \
    && apt-get -y install nodejs \
    && apt-get -qq install --yes --no-install-recommends sudo curl openssh-client rsync \
    && apt-get -qq install -y --no-install-recommends \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libldap-dev \
        libzip-dev \
        gnupg \
        nano \
        bash sshpass \
    && docker-php-ext-configure gd \
        --with-freetype=/usr/include/ \
        --with-jpeg=/usr/include/ \
        --with-webp=/usr/include \
    && NPROC=$(grep -c ^processor /proc/cpuinfo 2>/dev/null || 1) \
    && docker-php-ext-install -j${NPROC} gd \
    && docker-php-ext-install -j${NPROC} zip \
    && docker-php-ext-install -j${NPROC} ldap \
    && docker-php-ext-install -j${NPROC} pdo_mysql

RUN pecl install xdebug-3.1.2 \
    && docker-php-ext-enable xdebug

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=2.3.5

ADD ./php.ini /usr/local/etc/php/php.ini