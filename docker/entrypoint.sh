#!/bin/sh
set -e

# Default PORT to 10000 (Render web service default) if not provided
PORT="${PORT:-10000}"
export PORT

# Render PostgreSQL connection URL mapping
# Render sets DATABASE_URL for Postgres instances; Laravel supports DB_URL.
if [ -n "$DATABASE_URL" ] && [ -z "$DB_URL" ]; then
    export DB_URL="$DATABASE_URL"
    if [ -z "$DB_CONNECTION" ]; then
        export DB_CONNECTION="pgsql"
    fi
fi

# Dynamically configure Nginx listen port from template
if [ -f /etc/nginx/http.d/default.conf.template ]; then
    sed "s/__PORT__/${PORT}/g" /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf
fi

# Ensure storage directories exist with appropriate permissions
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create public storage symlink inside the container
php artisan storage:link --force 2>/dev/null || true

# Warm up / cache config and routes if APP_KEY is provided and caching is not disabled
if [ -n "$APP_KEY" ] && [ "${SKIP_CACHE:-false}" != "true" ]; then
    echo "Caching Laravel configuration, routes, and views..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
    php artisan event:cache || true
fi

# Automated database migrations if enabled via environment variable
if [ "${RUN_MIGRATIONS:-false}" = "true" ] || [ "${AUTORUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

echo "Starting container on port ${PORT}..."

exec "$@"
