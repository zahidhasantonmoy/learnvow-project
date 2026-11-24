#!/bin/bash
# LearnVow — Northflank container entrypoint.
# Waits for MySQL, publishes assets, migrates, seeds demo data once
# (only when the users table is empty), caches config/routes/views,
# then starts supervisord (nginx + PHP-FPM).
# Every step is idempotent — safe to re-run on container restarts.
set -e

PORT="${PORT:-8080}"

# 1. Nginx must listen on Northflank's $PORT.
sed -i "s/listen __PORT__/listen ${PORT}/" /etc/nginx/sites-available/default
echo "==> nginx will listen on port ${PORT}"

# 2. Storage dirs (the persistent volume is mounted at
#    /var/www/html/storage after the image is built, so (re)create the
#    dirs and fix ownership on every start).
mkdir -p storage/app/private/bookfiles storage/app/public \
    storage/framework/cache storage/framework/sessions \
    storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R 775 storage bootstrap/cache || true

# 3. Fail fast if the database env vars are missing.
: "${DB_HOST:?DB_HOST is not set — add the MySQL addon env vars}"
: "${DB_DATABASE:?DB_DATABASE is not set}"
: "${DB_USERNAME:?DB_USERNAME is not set}"
: "${DB_PASSWORD:?DB_PASSWORD is not set}"
DB_PORT="${DB_PORT:-3306}"

# 4. Wait for MySQL to accept connections.
echo "==> waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
    echo "    MySQL not ready yet, retrying in 2s..."
    sleep 2
done
echo "==> MySQL is reachable."

# 5. Publish package assets (Filament etc.) + discover packages.
php artisan package:discover --ansi
php artisan vendor:publish --tag=laravel-assets --ansi --force
php artisan storage:link || true

# 6. Migrate (idempotent) and seed demo data ONLY on a fresh DB.
php artisan migrate --force

USER_COUNT="$(php /usr/local/bin/seed-if-empty.php)"
if [ "$USER_COUNT" = "0" ]; then
    echo "==> fresh database — seeding demo data..."
    php artisan db:seed --force
else
    echo "==> database already has ${USER_COUNT} user(s) — skipping seed."
fi

# 7. Production caches. Verified locally: config:cache, route:cache and
#    view:cache all succeed on this codebase (Laravel 13.43.0).
#    Non-fatal — the app runs fine uncached, just slower.
php artisan config:cache || echo "!! config:cache failed — continuing uncached."
php artisan route:cache || echo "!! route:cache failed — continuing uncached."
php artisan view:cache || echo "!! view:cache failed — continuing uncached."

echo "==> starting supervisord (nginx + php-fpm)..."
exec "$@"
