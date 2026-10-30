#!/usr/bin/env bash
#
# Regenerate composer.lock and package-lock.json in throw-away Docker
# containers, so no PHP/Composer/Node toolchain is needed on the host
# (workspace rule: all building happens in Docker).
#
# Usage: scripts/update-locks.sh
# Run from anywhere; paths are resolved relative to this script.
set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
UID_GID="$(id -u):$(id -g)"

# PHP version required by composer.json ("php": "^X.Y"); used to decide
# whether the composer image's own PHP is new enough for resolution.
REQUIRED_PHP="8.5"

# Platform reqs that the throw-away composer image cannot satisfy but the
# runtime image (php:X.Y-apache, see Dockerfile) does: ext-apcu is compiled
# there, and the composer image's PHP may lag behind the required version.
IGNORE_FLAG=(--ignore-platform-req=ext-apcu)
echo "==> composer.lock (via composer:2)"
IMAGE_PHP="$(docker run --rm composer:2 php -r 'echo PHP_VERSION;')"
if [ "$(printf '%s\n%s\n' "$REQUIRED_PHP" "$IMAGE_PHP" | sort -V | head -n1)" != "$REQUIRED_PHP" ]; then
    echo "    composer image runs PHP $IMAGE_PHP (< $REQUIRED_PHP); ignoring the root php platform req"
    IGNORE_FLAG+=(--ignore-platform-req=php)
fi

docker run --rm \
    -u "$UID_GID" \
    -e HOME=/tmp \
    -e COMPOSER_HOME=/tmp/composer \
    -v "$PROJECT_DIR":/app -w /app \
    composer:2 \
    composer update --no-scripts --no-audit --no-interaction --with-all-dependencies "${IGNORE_FLAG[@]+"${IGNORE_FLAG[@]}"}"

# composer update also materialises vendor/ on the host; remove it (PHP deps
# live only inside the image, and the Docker build installs from the lock).
rm -rf "$PROJECT_DIR/vendor"

echo "==> package-lock.json (via node:24-alpine)"
docker run --rm \
    -u "$UID_GID" \
    -e npm_config_cache=/tmp/npm-cache \
    -v "$PROJECT_DIR":/app -w /app \
    node:24-alpine \
    npm install --package-lock-only --no-audit --no-fund

echo "==> Lock files updated. Rebuild with: docker compose build"
