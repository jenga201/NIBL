#!/usr/bin/env bash
#
# backup.sh - interactive dump/restore tooling for the niblmysql MySQL container.
#
# Commands:
#   dump             Dump ALL databases to backup/all-databases-<timestamp>.sql.gz
#                    (keeps the newest 7, prunes older automatically)
#   restore [file]   Restore from a backup (interactive picker when file omitted)
#   init             Import schema.sql + data.sql into the 'ooinuza' database
#   list             List available backups
#   (no argument)    Interactive menu
#
# Options:
#   -f, --force      Skip confirmation prompts (for non-interactive use)
#
# Credentials never touch the host: mysql/mysqldump run inside the container
# and use its MYSQL_ROOT_PASSWORD environment variable via MYSQL_PWD.

set -euo pipefail

CONTAINER="niblmysql"
# Must match the --socket flag in docker-compose.yml's mysqld command; the
# container runs as UID 1000 and cannot use the default /var/run/mysqld path.
MYSQL_SOCKET="/tmp/mysql.sock"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$SCRIPT_DIR/backup"
KEEP_BACKUPS=7

die()  { echo "ERROR: $*" >&2; exit 1; }
info() { echo "==> $*"; }

usage() {
  sed -n '3,17p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
}

require_container() {
  if [ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null || echo false)" != "true" ]; then
    die "container '$CONTAINER' is not running. Start it from the repo root: docker compose up -d"
  fi
}

# Run the mysql client inside the container as root; extra args are passed through.
mysql_exec() {
  docker exec -i -e MYSQL_SOCKET="$MYSQL_SOCKET" "$CONTAINER" \
    sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot --socket="$MYSQL_SOCKET" "$@"' sh "$@"
}

confirm() {
  local prompt="$1" reply
  if [ "$FORCE" = "1" ]; then return 0; fi
  echo
  read -r -p "$prompt [y/N] " reply
  if [[ ! "$reply" =~ ^[Yy]([Ee][Ss])?$ ]]; then die "Aborted."; fi
}

do_list() {
  local f found=0
  echo "Backups in $BACKUP_DIR:"
  for f in $(ls -1t "$BACKUP_DIR"/all-databases-*.sql.gz 2>/dev/null); do
    printf '  %-45s %8s  %s\n' "$(basename "$f")" "$(du -h "$f" | cut -f1)" "$(date -r "$f" '+%Y-%m-%d %H:%M:%S')"
    found=1
  done
  if [ "$found" = "0" ]; then echo "  (none)"; fi
}

do_dump() {
  require_container
  mkdir -p "$BACKUP_DIR"
  local ts file
  ts="$(date +%Y%m%d-%H%M%S)"
  file="$BACKUP_DIR/all-databases-$ts.sql.gz"
  info "Dumping all databases from '$CONTAINER' ..."
  if ! docker exec -e MYSQL_SOCKET="$MYSQL_SOCKET" "$CONTAINER" sh -c \
      'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --socket="$MYSQL_SOCKET" --all-databases --single-transaction --routines --triggers --events' \
      | gzip > "$file"; then
    rm -f "$file"
    die "Dump failed."
  fi
  if [ ! -s "$file" ]; then rm -f "$file"; die "Dump failed (empty output)."; fi
  info "Created $file ($(du -h "$file" | cut -f1))"
  # Prune: keep only the newest $KEEP_BACKUPS dumps.
  local old
  while IFS= read -r old; do
    if [ -n "$old" ]; then rm -f "$old" && info "Pruned old backup $(basename "$old")"; fi
  done < <(ls -1t "$BACKUP_DIR"/all-databases-*.sql.gz 2>/dev/null | tail -n +$((KEEP_BACKUPS + 1)))
}

do_restore() {
  require_container
  local file="${1:-}"
  if [ -z "$file" ]; then
    local -a backups=()
    mapfile -t backups < <(ls -1t "$BACKUP_DIR"/all-databases-*.sql.gz 2>/dev/null)
    if [ "${#backups[@]}" -eq 0 ]; then die "No backups found in $BACKUP_DIR."; fi
    echo "Available backups:"
    local i=1 f
    for f in "${backups[@]}"; do
      printf '  %2d) %-45s %8s  %s\n' "$i" "$(basename "$f")" "$(du -h "$f" | cut -f1)" "$(date -r "$f" '+%Y-%m-%d %H:%M:%S')"
      i=$((i + 1))
    done
    local sel
    read -r -p "Select backup to restore [1-${#backups[@]}] (b = back): " sel
    if [ "$sel" = "b" ]; then return 0; fi
    if ! [[ "$sel" =~ ^[0-9]+$ ]] || [ "$sel" -lt 1 ] || [ "$sel" -gt "${#backups[@]}" ]; then
      die "Invalid selection: $sel"
    fi
    file="${backups[$((sel - 1))]}"
  fi
  if [ ! -f "$file" ]; then die "Backup file not found: $file"; fi
  echo
  echo "WARNING: restoring '$(basename "$file")' will OVERWRITE every database in '$CONTAINER'."
  confirm "Continue with restore?"
  info "Restoring $file ..."
  gzip -dc "$file" | mysql_exec
  info "Restore complete."
}

do_init() {
  require_container
  local schema="$SCRIPT_DIR/schema.sql" data="$SCRIPT_DIR/data.sql"
  if [ ! -f "$schema" ]; then die "Missing $schema"; fi
  if [ ! -f "$data" ]; then die "Missing $data"; fi
  echo
  echo "This imports schema.sql + data.sql into database 'ooinuza'."
  echo "Existing tables in 'ooinuza' will be DROPPED and recreated."
  confirm "Continue with init import?"
  info "Importing schema.sql ..."
  mysql_exec < "$schema"
  info "Importing data.sql ..."
  mysql_exec < "$data"
  info "Init import complete. Summary:"
  mysql_exec -e "SELECT COUNT(*) AS tables_in_ooinuza FROM information_schema.tables WHERE table_schema='ooinuza';"
}

menu() {
  while true; do
    echo
    echo "niblmysql backup tool (container: $CONTAINER)"
    echo "  1) dump    - dump all databases into backup/"
    echo "  2) restore - restore from a backup file"
    echo "  3) init    - import schema.sql + data.sql into 'ooinuza'"
    echo "  4) list    - list available backups"
    echo "  q) quit"
    local c
    read -r -p "Choice: " c || exit 0
    case "$c" in
      1) ( do_dump )    || true ;;
      2) ( do_restore ) || true ;;
      3) ( do_init )    || true ;;
      4) ( do_list )    || true ;;
      q|Q) exit 0 ;;
      *) echo "Invalid choice." ;;
    esac
  done
}

# ---- argument parsing -------------------------------------------------------
FORCE=0
args=()
for a in "$@"; do
  case "$a" in
    -f|--force) FORCE=1 ;;
    -h|--help)  usage; exit 0 ;;
    *) args+=("$a") ;;
  esac
done
set -- "${args[@]}"

case "${1:-}" in
  dump)    do_dump ;;
  restore) do_restore "${2:-}" ;;
  init)    do_init ;;
  list)    do_list ;;
  "")      menu ;;
  *)       usage; exit 1 ;;
esac
