#!/bin/sh

echo "Starting Laravel application initialization..."

# Wait for database to be ready (optional)
echo "Waiting for database connection..."
sleep 5

# Clear Laravel caches to ensure environment variables are read correctly
echo "Clearing Laravel caches..."
php artisan config:clear
php artisan route:clear  
php artisan view:clear
php artisan cache:clear

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Cache configuration for better performance (with updated env vars)
echo "Caching configuration..."
php artisan config:cache
php artisan route:cache

# Ensure all required directories exist
echo "Creating required directories..."
mkdir -p storage/framework/views
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set proper permissions for storage and cache directories
echo "Setting permissions..."
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Test Firebase configuration
echo "Testing Firebase configuration..."
php artisan firebase:test-service || echo "Firebase test failed - continuing anyway"

echo "Laravel application initialization complete!"

# Start supervisord to run nginx and php-fpm
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf