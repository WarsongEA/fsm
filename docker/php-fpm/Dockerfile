FROM php:8-fpm-alpine

# Install packages
RUN apk add --no-cache curl  \
    git  \
    build-base  \
    zlib-dev  \
    oniguruma-dev  \
    autoconf  \
    bash

# Xdebug
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; \
    then \
      apk add --update linux-headers && \
      pecl install xdebug && docker-php-ext-enable xdebug; \
    fi;

COPY ./docker/php-fpm/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# GD
RUN apk add --no-cache curl  \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev

# GMP
RUN apk add --no-cache gmp gmp-dev
RUN docker-php-ext-install gmp

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Configure non-root user.
ARG PUID=1000
ARG PGID=1000
RUN apk --no-cache add shadow && \
    groupmod -o -g ${PGID} www-data && \
    usermod -o -u ${PUID} -g www-data www-data

# Install Node
RUN apk add --update nodejs npm
RUN npm install -g bower

# Source code
RUN chown www-data:www-data /var/www
COPY --chown=www-data:www-data ./ /var/www
WORKDIR /var/www

USER www-data
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

CMD php-fpm

EXPOSE 9000
