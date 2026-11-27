# syntax=docker/dockerfile:1
FROM php:8.4-cli-bookworm AS base

ARG APP_UID=1000
ARG APP_GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl git unzip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN groupadd --gid "${APP_GID}" app \
    && useradd --uid "${APP_UID}" --gid "${APP_GID}" --create-home --shell /bin/bash app \
    && mkdir -p /app/vendor /data \
    && chown -R app:app /app /data

WORKDIR /app

# Development image: dependencies are installed from composer.lock at build time.
# The /app/vendor named volume is initialised from this layer and kept in sync
# with composer.lock by the entrypoint (never with `composer update`).
FROM base AS dev

COPY --chmod=0755 docker/dev/entrypoint.sh /usr/local/bin/foodorder-entrypoint

USER app

COPY --chown=app:app composer.json composer.lock ./
RUN composer install --no-interaction --no-scripts --prefer-dist \
    && sha256sum composer.lock | cut -d' ' -f1 > vendor/.composer-lock.sha256

EXPOSE 8000

ENTRYPOINT ["foodorder-entrypoint"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
