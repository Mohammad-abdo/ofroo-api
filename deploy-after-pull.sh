#!/bin/bash
# تشغيل هذا السكربت على السيرفر بعد git pull لتحديث الكاش والاعتماديات
# Run this on the server after git pull to avoid issues

set -e
cd "$(dirname "$0")"

echo "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader

echo "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo "Running migrations (if any)..."
php artisan migrate --force

echo "Done. Application is ready."
