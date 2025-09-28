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

# Copy application code first
COPY . .

# 建立 Laravel 必要可寫目錄並設定權限
# - 建立 bootstrap/cache 與 storage 下常用子目錄
# - 將擁有者設為 www-data（PHP 預設使用者），避免執行期寫入受阻
# - 權限 775：同群組可寫，避免過度開放
RUN set -eux; \
    mkdir -p bootstrap/cache \
             storage/framework/{cache,data,sessions,views} \
             storage/logs \
             storage/framework/views; \
    chown -R www-data:www-data bootstrap storage; \
    chmod -R 775 bootstrap storage

# Install PHP dependencies with optimized settings
# Set PHP configuration for better performance during build
RUN echo "memory_limit = 2G" >> /usr/local/etc/php/conf.d/memory.ini && \
    echo "max_execution_time = 900" >> /usr/local/etc/php/conf.d/execution.ini && \
    echo "max_input_time = 900" >> /usr/local/etc/php/conf.d/execution.ini

# Set composer configuration for better performance and timeout handling
RUN composer config --global process-timeout 900 && \
    composer config --global cache-dir /tmp/composer-cache

# Install dependencies with optimized environment
RUN rm -f composer.lock && \
    COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=900 composer update --no-interaction --ignore-platform-req=ext-grpc --no-cache --no-scripts && \
    COMPOSER_MEMORY_LIMIT=-1 COMPOSER_PROCESS_TIMEOUT=900 composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-req=ext-grpc --no-cache --no-scripts

# Copy and run package discovery script
COPY docker/package-discover.sh /tmp/package-discover.sh
RUN chmod +x /tmp/package-discover.sh && \
    /tmp/package-discover.sh && \
    rm /tmp/package-discover.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Clear any existing Laravel cache to ensure environment variables are read correctly
# Note: This runs during build time, so environment variables from docker-compose won't be available
# We'll need to clear cache at runtime if needed
RUN php artisan config:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

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