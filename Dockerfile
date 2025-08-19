FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    unzip \
    autoconf \
    g++ \
    make \
    linux-headers \
    protobuf \
    protobuf-dev \
    && rm -rf /var/cache/apk/*

# Install PHP extensions
RUN pecl install openswoole grpc redis \
    && docker-php-ext-enable openswoole grpc redis \
    && docker-php-ext-install gmp pcntl \
    && rm -rf /tmp/pear

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files first for better caching
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --no-scripts --optimize-autoloader --no-interaction \
    && composer clear-cache

# Copy application files
COPY . .

# Create health check script
RUN echo '#!/bin/sh' > /app/bin/health-check.sh && \
    echo 'curl -f http://localhost:${REST_PORT:-8080}/health || exit 1' >> /app/bin/health-check.sh && \
    chmod +x /app/bin/health-check.sh

# Set permissions
RUN chmod +x /app/bin/*.php

# Health check
HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD /app/bin/health-check.sh || exit 1

# Default environment variables
ENV REST_HOST=0.0.0.0 \
    REST_PORT=8080 \
    GRPC_HOST=0.0.0.0 \
    GRPC_PORT=9080 \
    LOG_LEVEL=info

# Expose ports
EXPOSE 8080 9080

# Default to REST server
CMD ["php", "bin/rest-server.php"]