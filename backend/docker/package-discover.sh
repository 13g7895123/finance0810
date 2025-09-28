#!/bin/sh

# Package discovery script with timeout and retry logic
# This script handles the Laravel package discovery process more gracefully

set -e

echo "Starting Laravel package discovery..."

# Set PHP memory and execution limits
export PHP_MEMORY_LIMIT=2G
export PHP_MAX_EXECUTION_TIME=900

# Run package discovery with enhanced error handling
if ! php -d memory_limit=$PHP_MEMORY_LIMIT -d max_execution_time=$PHP_MAX_EXECUTION_TIME artisan package:discover --ansi --verbose 2>&1; then
    echo "Warning: Package discovery encountered issues, attempting fallback..."

    # Fallback: Clear all caches and try again
    php artisan config:clear >/dev/null 2>&1 || true
    php artisan cache:clear >/dev/null 2>&1 || true
    php artisan route:clear >/dev/null 2>&1 || true
    php artisan view:clear >/dev/null 2>&1 || true

    # Try package discovery again with minimum verbosity
    if ! php -d memory_limit=$PHP_MEMORY_LIMIT -d max_execution_time=$PHP_MAX_EXECUTION_TIME artisan package:discover 2>&1; then
        echo "Warning: Package discovery failed, but continuing build process..."
        echo "This may be resolved at runtime during container startup."
        exit 0
    fi
fi

echo "Package discovery completed successfully."