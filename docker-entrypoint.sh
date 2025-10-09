#!/bin/sh
set -e

cd /app

echo "===> [ENTRYPOINT] Start"
echo "===> [ENTRYPOINT] Checking .env"
ls -l /app/.env || true
cat /app/.env | grep APP_ENV || true

# Hapus symlink storage yang salah di root jika ada
if [ -L storage ]; then
    echo "===> [ENTRYPOINT] Removing storage symlink in /app"
    rm -f storage
fi

# Pastikan storage adalah direktori
if [ ! -d storage ]; then
    echo "===> [ENTRYPOINT] Creating storage directory"
    mkdir -p storage
fi

# Pastikan direktori penting ada
mkdir -p storage/app/public bootstrap/cache storage/framework/views storage/framework/cache storage/framework/sessions

# Pastikan storage link ada dan benar
if [ ! -L public/storage ]; then
    echo "===> [ENTRYPOINT] Recreating storage symlink"
    rm -rf public/storage || true
    ln -s ../storage/app/public public/storage
else
    echo "===> [ENTRYPOINT] Storage symlink already exists"
fi

# Perbaiki permission agar bisa diakses web server
echo "===> [ENTRYPOINT] Fixing permissions for storage and cache"
chmod -R 0775 storage bootstrap/cache public/storage || true

if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache public/storage || true
    chown -R www-data:www-data vendor || true
fi

ls -ld storage bootstrap/cache public/storage
ls -l public | grep storage
ls -ld storage/app/public || true
ls -ld storage/framework/views || true

# Install composer dependencies jika vendor belum ada
if [ ! -d vendor ]; then
    echo "===> [ENTRYPOINT] Running composer install"
    composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-interaction
fi

# Bersihkan cache Laravel
echo "===> [ENTRYPOINT] Clearing Laravel cache"
php artisan optimize:clear || true
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Reload Octane sebelum run (hindari state leak)
php artisan octane:reload || true

# Jalankan FrankenPHP dengan max request
echo "===> [ENTRYPOINT] Running FrankenPHP Octane server"
php artisan optimize:clear || true
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan jwt:secret --force || true

# Jalankan worker
if [ "$(id -u)" = "0" ]; then
    exec gosu www-data php artisan octane:frankenphp --host=0.0.0.0 --port=80 --max-requests=${MAX_REQUESTS:-25}
else
    exec php artisan octane:frankenphp --host=0.0.0.0 --port=80 --max-requests=${MAX_REQUESTS:-25}
fi