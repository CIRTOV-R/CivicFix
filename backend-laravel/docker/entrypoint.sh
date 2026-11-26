#!/bin/sh
set -e

cd /var/www/html

until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
    echo "Waiting for PostgreSQL/PostGIS..."
    sleep 2
done

php artisan migrate --force
php artisan db:seed --force
php artisan storage:link --force >/dev/null 2>&1 || true

exec "$@"
