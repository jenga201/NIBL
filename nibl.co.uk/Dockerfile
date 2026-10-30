# syntax=docker/dockerfile:1

###############################################################################
# assets: build the Tailwind CSS + JS bundles with Vite
###############################################################################
FROM node:24-alpine AS assets

WORKDIR /app

# Dependency manifests first, so this layer is cached until the lockfile changes
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY vite.config.js tsconfig.json ./
COPY resources ./resources
RUN npm run build
RUN mkdir -p public/vendor/swagger-ui && cp -r node_modules/swagger-ui-dist/. public/vendor/swagger-ui/

###############################################################################
# base: PHP runtime with only the extensions the app actually uses
# (curl, iconv, json, mbstring, openssl, simplexml and — since PHP 8.5 —
#  opcache are already compiled into the official image)
###############################################################################
FROM php:8.5-apache AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        zlib1g-dev \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        zip \
    && pecl install apcu \
    && docker-php-ext-enable apcu \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Use the default PHP production configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

###############################################################################
# vendor: production Composer dependencies (Composer itself never ships)
###############################################################################
FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1

# Official Composer image instead of wget + signature dance
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Manifests first, so the expensive install layer is cached until
# composer.lock changes
COPY composer.json composer.lock ./
RUN composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-dev \
        --no-scripts \
        --no-autoloader

# The optimized autoloader is generated once the application source is present
COPY . .
RUN composer dump-autoload --no-interaction --no-dev --optimize --classmap-authoritative

###############################################################################
# final: runtime image
###############################################################################
FROM base

WORKDIR /var/www/html

# Modify default apache configuration
COPY ./etc/docker/000-default.conf /etc/apache2/sites-available/000-default.conf

# Custom PHP adjustments (zz to make them the last being applied)
COPY ./etc/docker/php.ini.local $PHP_INI_DIR/conf.d/zz-usln.ini

# OPcache tuning for the immutable, baked-into-the-image application code
RUN printf '%s\n' \
        'opcache.enable=1' \
        'opcache.memory_consumption=128' \
        'opcache.interned_strings_buffer=16' \
        'opcache.max_accelerated_files=20000' \
        'opcache.validate_timestamps=0' \
        > "$PHP_INI_DIR/conf.d/zz-opcache.ini"

# Application source + production vendor, owned by www-data in one layer
# (no chown -R pass over the whole tree)
COPY --from=vendor --chown=www-data:www-data /var/www/html ./

# Freshly built frontend assets (Vite manifest + hashed bundles)
COPY --from=assets --chown=www-data:www-data /app/public/ ./public/

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD bash -c ':> /dev/tcp/127.0.0.1/${PORT:-80}' || exit 1

CMD ["/var/www/html/etc/docker/start-apache"]
