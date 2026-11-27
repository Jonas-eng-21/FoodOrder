#!/usr/bin/env bash
# Development entrypoint: environment guards, safe preparation, then the command.
set -euo pipefail

# --- Isolation guards (dev must never run with production markers) ---------
if [ -n "${FOODORDER_ENV_PROD:-}" ]; then
    echo "ERROR: FOODORDER_ENV_PROD is set; the development stack refuses production markers." >&2
    exit 1
fi
if [ "${APP_ENV:-}" != "local" ]; then
    echo "ERROR: APP_ENV must be 'local' in the development stack (got '${APP_ENV:-<unset>}')." >&2
    exit 1
fi

cd /app

# --- Persistent SQLite storage ------------------------------------------------
db_path="${DB_DATABASE:-/data/database.sqlite}"
if [ ! -w "$(dirname "$db_path")" ]; then
    echo "ERROR: $(dirname "$db_path") is not writable by $(id -un)." >&2
    exit 1
fi
[ -e "$db_path" ] || touch "$db_path"

# --- Dependencies: sync the vendor volume with composer.lock (no update) ------
lock_hash="$(sha256sum composer.lock | cut -d' ' -f1)"
stored_hash="$(cat vendor/.composer-lock.sha256 2>/dev/null || true)"
if [ ! -f vendor/autoload.php ] || [ "$lock_hash" != "$stored_hash" ]; then
    echo "Syncing vendor with composer.lock..."
    composer install --no-interaction --prefer-dist
    echo "$lock_hash" > vendor/.composer-lock.sha256
fi
if [ ! -f bootstrap/cache/packages.php ]; then
    php artisan package:discover --ansi
fi

# --- Server mode only: require key, migrate safely -----------------------------
if [ "${1:-}" = "php" ] && [ "${2:-}" = "artisan" ] && [ "${3:-}" = "serve" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "ERROR: APP_KEY is empty. Generate one with:" >&2
        echo "  docker compose run --rm --no-deps app php artisan key:generate --show" >&2
        echo "and set it in .env (see README)." >&2
        exit 1
    fi
    php artisan migrate --no-interaction
fi

exec "$@"
