# syntax=docker/dockerfile:1.4

FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    oniguruma-dev \
    libxml2-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    coreutils

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Set PHP configuration for better performance during build
RUN echo "memory_limit = 2G" >> /usr/local/etc/php/conf.d/memory.ini && \
    echo "max_execution_time = 900" >> /usr/local/etc/php/conf.d/execution.ini && \
    echo "max_input_time = 900" >> /usr/local/etc/php/conf.d/execution.ini

# Set composer configuration for better performance
RUN composer config --global process-timeout 900 && \
    composer config --global cache-dir /tmp/composer-cache

# Copy composer dependency files first for better layer caching
COPY composer.json composer.lock ./

# Install PHP dependencies with optimized settings
RUN COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=900 \
    composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --ignore-platform-req=ext-grpc \
        --prefer-dist \
        --no-scripts && \
    composer clear-cache

# Copy application code (this layer changes frequently)
COPY . .

# Copy and run package discovery script
COPY docker/package-discover.sh /tmp/package-discover.sh
RUN chmod +x /tmp/package-discover.sh && \
    /tmp/package-discover.sh && \
    rm /tmp/package-discover.sh

# Create Laravel required directories with proper permissions
RUN set -eux; \
    mkdir -p bootstrap/cache \
             storage/framework/{cache,data,sessions,views} \
             storage/logs \
             storage/framework/views; \
    chown -R www-data:www-data bootstrap storage; \
    chmod -R 775 bootstrap storage

# Note: Cache clearing is handled by startup.sh at runtime
# Removing build-time cache clearing to avoid "View path not found" errors
# when storage/framework/views doesn't exist on the host machine

# Copy Nginx configuration and startup script
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/startup.sh /startup.sh

# Make startup script executable
RUN chmod +x /startup.sh

# Create Nginx and supervisor directories
RUN mkdir -p /var/log/supervisor /run/nginx

EXPOSE 8000

CMD ["/startup.sh"]
