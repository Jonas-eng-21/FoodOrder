#!/usr/bin/env bash
# Production entrypoint: validates the environment, prepares SQLite, migrates safely, seeds the catalog
# idempotently, warms the Laravel caches (at runtime, with the real environment) and starts PHP-FPM.
set -euo pipefail

fail() {
    echo "ERROR: $*" >&2
    exit 1
}

check_environment() {
    [ "${FOODORDER_ENV_PROD-}" = "1" ] || fail "FOODORDER_ENV_PROD must be exactly 1 in the production stack."
    [ "${APP_ENV-}" = "production" ] || fail "APP_ENV must be 'production' (got '${APP_ENV-<unset>}')."
    [ "${APP_DEBUG-}" = "false" ] || fail "APP_DEBUG must be 'false' (got '${APP_DEBUG-<unset>}')."

    case "${APP_KEY-}" in
        base64:?*) ;;
        *) fail "APP_KEY is missing or malformed. Generate one with: scripts/prod/dcp.sh init-key" ;;
    esac
    local key_bytes
    key_bytes="$(printf '%s' "${APP_KEY#base64:}" | base64 -d 2>/dev/null | wc -c || true)"
    [ "${key_bytes:-0}" -eq 32 ] || fail "APP_KEY is not a valid 32-byte base64 key. Generate one with: scripts/prod/dcp.sh init-key"

    [ "${DB_CONNECTION-}" = "sqlite" ] || fail "DB_CONNECTION must be 'sqlite' (got '${DB_CONNECTION-<unset>}')."
    case "${DB_DATABASE-}" in
        /data/*.sqlite) ;;
        *) fail "DB_DATABASE must be a .sqlite file inside the /data volume (got '${DB_DATABASE-<unset>}')." ;;
    esac
}

if [ "${1-}" = "--check-env" ]; then
    check_environment
    echo "Production environment OK."
    exit 0
fi

check_environment

# Only the PHP-FPM start performs the initialisation; any other command just runs behind the guards.
if [ "${1-}" = "php-fpm" ]; then
    cd /app

    data_dir="$(dirname "$DB_DATABASE")"
    [ -w "$data_dir" ] || fail "$data_dir is not writable by $(id -un)."
    if [ ! -e "$DB_DATABASE" ]; then
        touch "$DB_DATABASE"
        chmod 600 "$DB_DATABASE"
    fi

    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views
    if [ -n "${APP_CONFIG_CACHE-}" ]; then
        mkdir -p "$(dirname "$APP_CONFIG_CACHE")"
    fi

    # Pending migrations only (never a reset) and the idempotent catalog seed; any failure stops the start.
    php artisan migrate --force --no-interaction
    php artisan db:seed --force --no-interaction

    # Caches are built here, at runtime, so they reflect the real production environment.
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
fi

exec "$@"
