FROM php:8.2-cli-alpine

# Install base packages and build dependencies
RUN apk add --no-cache \
    git \
    bash \
    curl \
    $PHPIZE_DEPS \
    openssl-dev \
    curl-dev \
    c-ares-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    gmp \
    gmp-dev \
    oniguruma-dev \
    autoconf \
    build-base \
    zlib-dev

# Install OpenSwoole extension
RUN pecl install openswoole && \
    docker-php-ext-enable openswoole

# Install other PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd gmp mbstring pdo pdo_mysql

# Configure non-root user
ARG PUID=1000
ARG PGID=1000
RUN apk --no-cache add shadow && \
    groupmod -o -g ${PGID} www-data && \
    usermod -o -u ${PUID} -g www-data www-data

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy source code
COPY --chown=www-data:www-data ./ /var/www

# Switch to non-root user
USER www-data

# Expose OpenSwoole default port
EXPOSE 9501

# Default command to run OpenSwoole server
CMD ["php", "/var/www/bin/server.php"]