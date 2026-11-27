#!/usr/bin/env bash
# Guarded wrapper around `docker compose` for the FoodOrder production stack.
#
# It always targets compose.prod.yaml, .env.prod and the project "foodorder-prod", refuses inconsistent
# environments, accepts only an allowlist of operational subcommands and flags, and rejects anything that
# could delete volumes or redirect the stack (down -v, run/exec/rm, -f/-p/--env-file overrides...).
# It protects against operational mistakes; it is not a barrier against someone calling Docker directly.
set -euo pipefail

die() {
    echo "dcp: $*" >&2
    exit 1
}

readonly project="foodorder-prod"
readonly image="foodorder-prod-app:latest"
script_path="$(readlink -f -- "${BASH_SOURCE[0]}")"
root="$(cd -- "$(dirname -- "$script_path")/../.." && pwd -P)"
readonly root
readonly compose_file="$root/compose.prod.yaml"
readonly env_file="$root/.env.prod"

# Compose selectors inherited from the shell must never redirect this stack.
unset COMPOSE_FILE COMPOSE_PROJECT_NAME COMPOSE_PATH_SEPARATOR COMPOSE_PROFILES COMPOSE_ENV_FILES COMPOSE_DISABLE_ENV_FILE

usage() {
    cat >&2 <<'USAGE'
Usage: scripts/prod/dcp.sh <command> [options]

Commands: config, build, up, ps, logs, restart, start, stop, down, images, top, init-key
  up -d --build          build and start the production stack
  down                   stop and remove containers (volumes are always preserved)
  init-key               generate APP_KEY into .env.prod when it is still empty
Destructive or arbitrary operations (down -v, run, exec, rm, -f/-p overrides...) are rejected.
USAGE
    exit 2
}

# Last NAME=value of .env.prod, without surrounding quotes (the file is parsed, never sourced).
env_value() {
    local line value
    line="$(grep -E "^$1=" "$env_file" | tail -n 1 || true)"
    value="${line#*=}"
    value="${value%\"}"
    value="${value#\"}"
    value="${value%\'}"
    value="${value#\'}"
    printf '%s' "$value"
}

validate_environment() {
    [ -f "$compose_file" ] || die "compose.prod.yaml not found in $root."
    [ -r "$env_file" ] || die ".env.prod not found. Create it with: cp .env.prod.example .env.prod"

    [ "$(env_value FOODORDER_ENV_PROD)" = "1" ] || die "FOODORDER_ENV_PROD must be exactly 1 in .env.prod."
    [ "$(env_value APP_ENV)" = "production" ] || die "APP_ENV must be 'production' in .env.prod."
    [ "$(env_value APP_DEBUG)" = "false" ] || die "APP_DEBUG must be 'false' in .env.prod."
    [ "$(env_value DB_CONNECTION)" = "sqlite" ] || die "DB_CONNECTION must be 'sqlite' in .env.prod."

    local name
    for name in FOODORDER_ENV_PROD APP_ENV APP_DEBUG APP_KEY APP_URL APP_NAME DB_CONNECTION DB_DATABASE PROD_PORT LOG_CHANNEL SESSION_DRIVER CACHE_STORE; do
        if [ -n "${!name+x}" ] && [ "${!name}" != "$(env_value "$name")" ]; then
            die "the exported variable $name conflicts with .env.prod; unset it and retry."
        fi
    done
}

contains() {
    local needle="$1"
    shift
    local item
    for item in "$@"; do
        [ "$item" = "$needle" ] && return 0
    done
    return 1
}

# Validates the arguments of a subcommand against an allowlist and fills the global "forwarded" array.
declare -a forwarded=()
validate_arguments() {
    local sub="$1"
    shift
    local -a bool_flags=() value_flags=()
    local services=0

    case "$sub" in
        config) bool_flags=(-q --quiet --services --images) ;;
        build) bool_flags=(--no-cache --pull -q --quiet); services=1 ;;
        up) bool_flags=(-d --detach --build --no-build --force-recreate --no-recreate --no-start --wait --no-deps); value_flags=(-t --timeout --wait-timeout); services=1 ;;
        down) value_flags=(-t --timeout) ;;
        ps) bool_flags=(-a --all -q --quiet --services); value_flags=(--format --status); services=1 ;;
        logs) bool_flags=(-f --follow --no-color --no-log-prefix --timestamps); value_flags=(--tail -n --since --until); services=1 ;;
        restart | start | stop) value_flags=(-t --timeout); services=1 ;;
        images | top) services=1 ;;
        *) die "unsupported command '$sub'. Allowed: config build up ps logs restart start stop down images top init-key" ;;
    esac

    local token name value
    while [ "$#" -gt 0 ]; do
        token="$1"
        shift
        case "$token" in
            --*)
                name="${token%%=*}"
                if contains "$name" "${bool_flags[@]+"${bool_flags[@]}"}"; then
                    [ "$name" = "$token" ] || die "flag $name does not take a value."
                    forwarded+=("$token")
                elif contains "$name" "${value_flags[@]+"${value_flags[@]}"}"; then
                    if [ "$name" = "$token" ]; then
                        [ "$#" -gt 0 ] || die "flag $name needs a value."
                        value="$1"
                        shift
                    else
                        value="${token#*=}"
                    fi
                    [[ "$value" =~ ^[0-9A-Za-z_.:-]+$ ]] || die "invalid value for $name."
                    forwarded+=("$token")
                    [ "$name" != "$token" ] || forwarded+=("$value")
                else
                    die "flag $name is not allowed for '$sub'."
                fi
                ;;
            -?)
                if contains "$token" "${bool_flags[@]+"${bool_flags[@]}"}"; then
                    forwarded+=("$token")
                elif contains "$token" "${value_flags[@]+"${value_flags[@]}"}"; then
                    [ "$#" -gt 0 ] || die "flag $token needs a value."
                    value="$1"
                    shift
                    [[ "$value" =~ ^[0-9A-Za-z_.:-]+$ ]] || die "invalid value for $token."
                    forwarded+=("$token" "$value")
                else
                    die "flag $token is not allowed for '$sub'."
                fi
                ;;
            -*)
                die "combined or unknown short flags ($token) are not allowed; pass each flag separately."
                ;;
            *)
                [ "$services" -eq 1 ] || die "'$sub' does not take positional arguments."
                contains "$token" app web || die "unknown service '$token' (allowed: app, web)."
                forwarded+=("$token")
                ;;
        esac
    done
}

[ "$#" -gt 0 ] || usage
sub="$1"
shift
case "$sub" in
    -*) die "options before the command (compose overrides such as -f, -p, --env-file) are not accepted." ;;
    help) usage ;;
esac

validate_environment

if [ "$sub" = "init-key" ]; then
    [ "$#" -eq 0 ] || die "init-key takes no arguments."
    [ -z "$(env_value APP_KEY)" ] || die "APP_KEY is already set in .env.prod; refusing to replace it."
    key="$(docker run --rm --entrypoint php "$image" artisan key:generate --show | tail -n 1 | tr -d '\r')"
    case "$key" in
        base64:?*) ;;
        *) die "could not generate a key. Build the image first: scripts/prod/dcp.sh build" ;;
    esac
    tmp="$(mktemp "$env_file.XXXXXX")"
    KEY="$key" awk '/^APP_KEY=/ { print "APP_KEY=" ENVIRON["KEY"]; next } { print }' "$env_file" > "$tmp"
    chmod 600 "$tmp"
    mv -- "$tmp" "$env_file"
    echo "APP_KEY written to .env.prod (value not displayed)."
    exit 0
fi

validate_arguments "$sub" "$@"

# `config` without --quiet would print the resolved environment (including APP_KEY): keep secrets out of it.
if [ "$sub" = "config" ] && ! contains -q "${forwarded[@]+"${forwarded[@]}"}" && ! contains --quiet "${forwarded[@]+"${forwarded[@]}"}"; then
    forwarded+=(--no-env-resolution)
fi

compose=(docker compose --project-name "$project" --project-directory "$root" --env-file "$env_file" -f "$compose_file")

case "$sub" in
    up | build | restart | start)
        "${compose[@]}" config --quiet > /dev/null || die "the production compose configuration is invalid."
        ;;
esac

exec "${compose[@]}" "$sub" "${forwarded[@]+"${forwarded[@]}"}"
