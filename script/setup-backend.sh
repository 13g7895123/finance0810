#!/bin/bash

echo "=========================================="
echo "Finance CRM - Laravel Backend Setup"
echo "=========================================="

cd backend

echo "1. Installing PHP dependencies..."
if command -v composer &> /dev/null; then
    composer install --optimize-autoloader
else
    echo "Composer not found. Please install Composer first."
    exit 1
fi

echo "2. Generating application key..."
php artisan key:generate

echo "3. Running database migrations..."
php artisan migrate --force

echo "4. Seeding database with initial data..."
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=AdminUserSeeder

echo "5. Clearing caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "6. Creating symbolic link for storage..."
php artisan storage:link

echo "7. Testing basic connectivity..."
echo "Testing health endpoint..."
curl -s http://localhost:8000/api/health || echo "Backend not accessible yet"

echo "=========================================="
echo "Backend setup completed!"
echo ""
echo "Default users created:"
echo "- Admin: admin@finance-crm.com / password123"
echo "- Executive: executive@finance-crm.com / password123"  
echo "- Manager: manager@finance-crm.com / password123"
echo "- Staff: staff@finance-crm.com / password123"
echo ""
echo "IMPORTANT: Change default passwords in production!"
echo "=========================================="