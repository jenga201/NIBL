#!/usr/bin/env bash
#
# migrate.sh — prepare the ooinuza database on this host and bring its schema
# up to date (idempotent; safe to run on every deploy).
#
#   1. If ooinuza has no tables, restore the newest backup/all-databases-*.sql.gz.
#   2. Apply pending migrations from migrations/*.sql in filename order,
#      recording applied filenames in ooinuza.schema_migrations.
#
# Credentials never touch the host: mysql runs inside the niblmysql container
# and uses its MYSQL_ROOT_PASSWORD environment variable via MYSQL_PWD (the same
# pattern as backup.sh).
set -euo pipefail

CONTAINER="niblmysql"
MYSQL_SOCKET="/tmp/mysql.sock"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MIGRATIONS_DIR="$SCRIPT_DIR/migrations"
BACKUP_DIR="$SCRIPT_DIR/backup"

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
  echo "==> ooinuza is empty; restoring $(basename "$latest")"
  gzip -dc "$latest" | mysql_exec
else
  echo "==> ooinuza already has tables; skipping backup restore"
fi

echo "==> ensuring migrations tracking table"
mysql_exec -e "CREATE TABLE IF NOT EXISTS ooinuza.schema_migrations (filename VARCHAR(255) NOT NULL PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;"

if [ -d "$MIGRATIONS_DIR" ]; then
  shopt -s nullglob
  for f in "$MIGRATIONS_DIR"/*.sql; do
    name=$(basename "$f")
    applied=$(mysql_exec -N -e "SELECT COUNT(*) FROM ooinuza.schema_migrations WHERE filename='$name';" | tr -d '[:space:]')
    if [ "$applied" = "0" ]; then
      echo "==> applying migration $name"
      mysql_exec ooinuza < "$f"
      mysql_exec -e "INSERT INTO ooinuza.schema_migrations (filename) VALUES ('$name');"
    else
      echo "==> skip migration $name (already applied)"
    fi
  done
fi

echo "==> migrate.sh complete"