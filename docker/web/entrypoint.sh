#!/bin/sh
set -e

# Génère APP_KEY si absent
if [ -z "${APP_KEY:-}" ] || [ "$APP_KEY" = "base64:CHANGE_ME" ]; then
  echo "[entrypoint] Generating APP_KEY..."
  php artisan key:generate --show > /tmp/.app_key
  export APP_KEY="$(cat /tmp/.app_key)"
fi

# Wait pour Postgres (DB_HOST défini)
if [ -n "${DB_HOST:-}" ] && [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
  echo "[entrypoint] Waiting for DB ${DB_HOST}:${DB_PORT:-5432}..."
  for i in $(seq 1 30); do
    php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT:-5432}) ? 0 : 1);" && break
    sleep 1
  done
fi

# Migrations
php artisan migrate --force --no-interaction || echo "[entrypoint] migrations failed (ok si premier boot sans DB)"

# Installation idempotente : crée le domaine MAIL_DOMAIN + compte admin
# ADMIN_EMAIL si absents. Indispensable pour survivre à un `docker compose
# down -v` qui détruit le volume Postgres.
php artisan mail:install 2>&1 || echo "[entrypoint] mail:install failed (ok si DB pas prête)"

# Cache
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Storage symlink
[ -L /var/www/html/public/storage ] || php artisan storage:link || true

exec "$@"
