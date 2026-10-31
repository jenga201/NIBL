#!/usr/bin/env bash
#
# bootstrap.sh — prepare ooinuza on this host before Laravel migrations run:
#   1. If ooinuza has no tables, restore the newest all-databases-*.sql.gz.
#   2. Provision the ooinuza account with the modern auth plugin
#      (caching_sha2_password) from the start, and fix any legacy accounts.
#
# Schema ownership now lives in nibl.co.uk/database/migrations (php artisan
# migrate); this script only bootstraps what Laravel cannot do as the app user.
# Credentials never touch the host filesystem: they come from the shared .env
# and are injected straight into the mysql client.
set -euo pipefail

CONTAINER="niblmysql"
MYSQL_SOCKET="/tmp/mysql.sock"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/backup"
ENV_FILE="$SCRIPT_DIR/../.env"

mysql_exec() {
  docker exec -i -e MYSQL_SOCKET="$MYSQL_SOCKET" "$CONTAINER" \
    sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --socket="$MYSQL_SOCKET" "$@"' sh "$@"
}

echo "==> checking ooinuza tables"
table_count=$(mysql_exec -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='ooinuza';" | tr -d '[:space:]')

if [ "$table_count" = "0" ]; then
  latest=$(ls -1t "$BACKUP_DIR"/all-databases-*.sql.gz 2>/dev/null | head -1 || true)
  if [ -z "$latest" ]; then
    echo "ERROR: ooinuza is empty and no all-databases-*.sql.gz backup found in $BACKUP_DIR" >&2
    exit 1
  fi
  echo "==> restoring $(basename "$latest")"
  gzip -dc "$latest" | mysql_exec
else
  echo "==> ooinuza already has tables; skipping backup restore"
fi

echo "==> provisioning app user"
[ -s "$ENV_FILE" ] || { echo "ERROR: $ENV_FILE is missing or empty" >&2; exit 1; }
SQL_USER=$(grep -E '^SQL_USER=' "$ENV_FILE" | tail -n1 | cut -d= -f2-)
SQL_PASSWORD=$(grep -E '^SQL_PASSWORD=' "$ENV_FILE" | tail -n1 | cut -d= -f2-)
: "${SQL_USER:?SQL_USER missing from .env}"
: "${SQL_PASSWORD:?SQL_PASSWORD missing from .env}"

user_count=$(mysql_exec -N -e "SELECT COUNT(*) FROM mysql.user WHERE User='${SQL_USER}' AND Host='%'" | tr -d '[:space:]')
if [ "$user_count" = "0" ]; then
  echo "==> creating app user ${SQL_USER} with caching_sha2_password"
  mysql_exec -e "CREATE USER '${SQL_USER}'@'%' IDENTIFIED WITH caching_sha2_password BY '${SQL_PASSWORD}'"
else
  echo "==> app user ${SQL_USER} already exists"
fi
mysql_exec -e "ALTER USER '${SQL_USER}'@'%' IDENTIFIED WITH caching_sha2_password BY '${SQL_PASSWORD}'"
mysql_exec -e "GRANT ALL PRIVILEGES ON ooinuza.* TO '${SQL_USER}'@'%'"
mysql_exec -e "FLUSH PRIVILEGES"

echo "==> bootstrap.sh complete"