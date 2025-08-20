FROM php:8.3-cli-alpine

# Install base packages and build dependencies
RUN apk add --no-cache \
    git \
    bash \
    curl \
    $PHPIZE_DEPS \
    openssl-dev \
    curl-dev \
    c-ares-dev \
    gmp \
    gmp-dev \
    autoconf \
    build-base \
    zlib-dev

# Install OpenSwoole extension
RUN pecl install openswoole && \
    docker-php-ext-enable openswoole

# Install other PHP extensions
RUN docker-php-ext-install gmp pcntl

# Xdebug installation
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; then \
    apk add --no-cache linux-headers; \
    pecl install xdebug-3.3.2; \
    docker-php-ext-enable xdebug; \
fi

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

# Default command to keep container running  
CMD ["sleep", "infinity"]