#!/bin/sh
set -e

# Render wstrzykuje $PORT (domyślnie 10000). Wstaw go do configu nginx.
: "${PORT:=10000}"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/sites-enabled/default

# Migracje + cache produkcyjny (config/route/view).
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
