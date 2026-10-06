# syntax=docker/dockerfile:1
# Development image for the Laravel backend (PHP-FPM, also used for Horizon, artisan and tests).
FROM php:8.5-fpm-alpine

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY --from=ghcr.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN apk add --no-cache git unzip postgresql-client \
    && install-php-extensions pdo_pgsql pgsql redis pcntl posix intl zip bcmath opcache

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-trophyvault.ini

ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app 2>/dev/null || true; \
    adduser -D -u ${UID} -G "$(getent group ${GID} | cut -d: -f1)" app 2>/dev/null || true; \
    sed -i 's/^user = .*/user = app/; s/^group = .*/group = app/' /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || true

WORKDIR /var/www/html
USER app
