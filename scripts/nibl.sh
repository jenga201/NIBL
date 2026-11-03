#!/usr/bin/env bash
#
# nibl.sh — terminal UI to manage the NIBL production stack (niblmysql,
# niblirc, niblcouk, swag, meilisearch) from a single Git checkout on the host.
#
# Run on your local machine:
#     bash nibl.sh
#
# Menu
#   1) Deploy  — full pipeline: clone/pull, seed secrets + backups, enable
#                Docker IPv6 (first run), build + start the stack, apply
#                migrations + Meilisearch backfill, then verify over HTTPS.
#   2) Rebuild — pull the latest code, rebuild images, restart, migrate and
#                re-sync Meilisearch. Skips first-time setup and verification
#                (assumes a prior Deploy).
#   3) Logs    — follow live container logs on the host (`docker compose logs -f`).
#   4) Diagnostics — export every container's logs, a full MySQL dump, the
#                host `logs/` tree, and the on-disk botlists into a timestamped
#                folder locally for offline review.
#   5) Prune   — reclaim disk space: remove stopped containers, unused networks,
#                dangling images, and the build cache (`docker system prune`).
#   6) SQL     — open an interactive MySQL shell on niblmysql as root.
#   q) Quit
#
# It does NOT git commit/push — you do that first and push.
set -uo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

# Remote SSH target: "user@host" or an alias from ~/.ssh/config.
REMOTE_SSH="nibl"

# Forward the local ssh-agent across the SSH connection. Only needed if the host
# clones via SSH — the default HTTPS clone needs no agent.
SSH_USE_AGENT_FORWARDING="false"

# Remote checkout root and Git details for the single combined repository.
REMOTE_DIR="/opt/NIBL"
REPO_URL="https://github.com/jenga201/NIBL.git"
REMOTE_BRANCH="main"   # production branch; merge develop -> main for releases

# Paths to the shared .env (the gitignored secrets file).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOCAL_ENV_FILE="$SCRIPT_DIR/../.env"      # on the machine running this script
REMOTE_ENV_FILE="$REMOTE_DIR/.env"        # on the production host

# Local MySQL backups, uploaded on the first deploy so bootstrap.sh can restore
# the database when the fresh host's database is empty.
LOCAL_BACKUP_DIR="$SCRIPT_DIR/../niblmysql/backup"

# HTTP verification targets (proxied through swag, Deploy only).
SITE_HOST="www.nibl.co.uk"
SITE_URL="https://www.nibl.co.uk"
API_URL="https://www.nibl.co.uk/api/bots"

# Compose services offered by the Logs menu (empty input = all services).
SERVICES=(niblmysql niblirc niblcouk swag meilisearch niblscheduler)

# Diagnostics: where bundles land locally, and how much history to export per
# service. Set DIAG_LOG_TAIL to a number (e.g. 200000) to cap very long logs;
# "all" exports everything Docker still holds.
LOCAL_LOG_EXPORT_DIR="$SCRIPT_DIR/../logs-export"
DIAG_LOG_TAIL="all"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

[ -n "$REMOTE_SSH" ] || { echo "ERROR: set REMOTE_SSH at the top of $0" >&2; exit 1; }

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=15)
[ "$SSH_USE_AGENT_FORWARDING" = "true" ] && SSH_OPTS+=(-A)

say()  { printf '\n\033[1;36m==>\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Menu
# ---------------------------------------------------------------------------

menu_title() {
  printf '\n\033[1;34m  NIBL — production stack\033[0m   remote: \033[1;33m%s\033[0m\n\n' "$REMOTE_SSH"
  printf '  \033[1m1\033[0m  Deploy   full pipeline (pull, seed, build, migrate, verify)\n'
  printf '  \033[1m2\033[0m  Rebuild  pull + build + migrate/sync (no first-time setup)\n'
  printf '  \033[1m3\033[0m  Logs     follow live container logs\n'
  printf '  \033[1m4\033[0m  Diagnostics  export logs + DB dump + logs/ + botlists locally\n'
  printf '  \033[1m5\033[0m  Prune     recover disk space (stopped containers, images, cache)\n'
  printf '  \033[1m6\033[0m  SQL       interactive MySQL shell on niblmysql (root)\n'
  printf '  \033[1mq\033[0m  Quit\n\n'
}

# Run an action so a failure returns to the menu instead of killing the shell.
# The body runs in a subshell with errexit, so `die`/`exit` inside only aborts
# that one action.
action() {
  local name="$1"; shift
  say "$name"
  if ( set -euo pipefail; "$@" ); then
    printf '\n\033[1;32m%s: done\033[0m\n' "$name"
  else
    printf '\n\033[1;31m%s: failed (see output above)\033[0m\n' "$name"
  fi
}

# ---------------------------------------------------------------------------
# Shared steps
# ---------------------------------------------------------------------------

preflight() {
  say "Preflight"
  command -v ssh >/dev/null || die "ssh not found"
  command -v scp >/dev/null || die "scp not found"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'docker version >/dev/null 2>&1' \
    || die "docker is not reachable on $REMOTE_SSH"
}

remote_ensure_dir() {
  say "Remote directory"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' "$REMOTE_DIR" <<'REMOTE_EOF'
set -euo pipefail
root=$1
mkdir -p "$root"
REMOTE_EOF
}

# 0 when the repository does not yet exist on the host (fresh checkout).
is_first_checkout() {
  ! ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "[ -d '$REMOTE_DIR/.git' ]"
}

git_sync() {
  say "Clone/pull the NIBL repository"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' \
    "$REMOTE_DIR" "$REPO_URL" "$REMOTE_BRANCH" <<'REMOTE_EOF'
set -euo pipefail
root=$1; repo=$2; branch=$3
export GIT_TERMINAL_PROMPT=0
if [ -d "$root/.git" ]; then
  echo "pulling $branch in $root"
  git -C "$root" fetch origin
  git -C "$root" checkout "$branch"
  git -C "$root" reset --hard "origin/$branch"
else
  echo "cloning $repo into $root"
  git clone --branch "$branch" "$repo" "$root"
fi
REMOTE_EOF
}

seed_secrets_and_backups() {
  if is_first_checkout; then
    say "First checkout — copying local .env to the host"
    [ -s "$LOCAL_ENV_FILE" ] || die "local .env is missing or empty: $LOCAL_ENV_FILE"
    scp -q -o BatchMode=yes -o ConnectTimeout=15 "$LOCAL_ENV_FILE" "$REMOTE_SSH:$REMOTE_ENV_FILE"
    ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "chmod 600 '$REMOTE_ENV_FILE'"
  else
    say "Existing checkout — keeping the host's .env"
  fi

  # The .env must exist before docker compose runs (just copied, or pre-existing).
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "[ -s '$REMOTE_ENV_FILE' ]" \
    || die "$REMOTE_ENV_FILE is missing or empty"

  # Seed MySQL backups if the host has none yet — bootstrap.sh needs one to restore
  # an empty database. This also covers a re-deploy onto a freshly cloned host.
  if ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' "$REMOTE_DIR" <<'REMOTE_EOF'
root=$1
ls "$root/niblmysql/backup"/all-databases-*.sql.gz >/dev/null 2>&1
REMOTE_EOF
  then
    say "Host already has MySQL backups — keeping them"
  elif [ -d "$LOCAL_BACKUP_DIR" ]; then
    say "Uploading local MySQL backups to the host"
    scp -r -q -o BatchMode=yes -o ConnectTimeout=60 "$LOCAL_BACKUP_DIR" "$REMOTE_SSH:$REMOTE_DIR/niblmysql/"
  else
    say "No local MySQL backups to upload"
  fi
}

enable_ipv6() {
  say "Enabling Docker IPv6 on the host (enter the sudo password when prompted)"
  ssh -t "${SSH_OPTS[@]}" "$REMOTE_SSH" \
    "sudo bash '$REMOTE_DIR/scripts/setup-docker-ipv6.sh'" \
    || die "Docker IPv6 setup failed"
}

# Build + start + migrate + Meili sync, shared by Deploy and Rebuild.
build_and_sync() {
  say "Build + start the stack (niblmysql -> niblirc + niblcouk -> swag)"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' "$REMOTE_DIR" <<'REMOTE_EOF'
set -euo pipefail
cd "$1"
mkdir -p data/niblmysql data/nibl.co.uk data/niblirc data/swag data/meilisearch logs/niblmysql logs/nibl.co.uk logs/niblirc logs/swag
chown -R 1000:1000 data/niblmysql data/meilisearch logs/niblmysql

# Create the shared external network now (after Docker IPv6 is enabled above) so
# it comes up dual-stack.
docker network ls --format '{{.Name}}' | grep -qx my-bridge \
  || docker network create -d bridge my-bridge

docker compose build --pull < /dev/null

# Bring MySQL up first, then provision the app user before anything touches the
# schema.
docker compose up -d niblmysql < /dev/null

# Wait for the niblmysql container's own healthcheck to report healthy.
status="none"
for _ in $(seq 1 36); do
  status=$(docker inspect -f '{{.State.Health.Status}}' niblmysql 2>/dev/null || echo none)
  if [ "$status" = "healthy" ]; then
    break
  fi
  sleep 5
done
[ "$status" = "healthy" ] || { echo "ERROR: niblmysql not healthy after waiting"; exit 1; }
echo "niblmysql: $status"

bash niblmysql/bootstrap.sh < /dev/null

# Apply pending migrations in a side-load container before the app containers
# come up. (niblcouk also self-migrates on startup as a backstop for a bare
# `docker compose up`.)
docker compose run -T --rm niblcouk php artisan migrate --force < /dev/null

# Bring up the whole stack — this recreates any container whose image changed,
# so niblcouk/niblirc end up running the freshly built images.
docker compose up -d --remove-orphans < /dev/null

# Wait for Meilisearch to be reachable, then backfill the index (later changes
# are picked up by the minute scheduler).
status="none"
for _ in $(seq 1 24); do
  status=$(docker inspect -f '{{.State.Health.Status}}' niblmeili 2>/dev/null || echo none)
  if [ "$status" = "healthy" ]; then
    break
  fi
  sleep 5
done
echo "meilisearch: $status"

docker compose run -T --rm niblcouk php artisan meilisearch:sync < /dev/null

# Report container states.
sleep 10
for c in niblirc niblcouk swag; do
  state=$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null || echo missing)
  echo "$c: $state"
done
docker ps --format '  {{.Names}}\t{{.Status}}'
REMOTE_EOF
}

verify() {
  say "Verifying"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' \
    "$SITE_URL" "$API_URL" "$SITE_HOST" <<'REMOTE_EOF'
set -uo pipefail
site_url=$1; api_url=$2; site_host=$3

echo "containers:"
docker ps --format '  {{.Names}}\t{{.Status}}'

site_code=$(curl -fsSk --max-time 20 --resolve "${site_host}:443:127.0.0.1" -o /dev/null -w '%{http_code}' "$site_url" || true)
api_code=$(curl -fsSk --max-time 20 --resolve "${site_host}:443:127.0.0.1" -o /dev/null -w '%{http_code}' "$api_url" || true)

echo "GET $site_url -> $site_code"
echo "GET $api_url -> $api_code"

[ "$site_code" = "200" ] && [ "$api_code" = "200" ] || {
  echo "ERROR: HTTP verification failed"
  exit 1
}
echo "Deploy verified OK."
REMOTE_EOF
}

# ---------------------------------------------------------------------------
# Actions
# ---------------------------------------------------------------------------

deploy() {
  preflight
  remote_ensure_dir

  local first
  if is_first_checkout; then first=1; else first=0; fi

  git_sync
  seed_secrets_and_backups
  [ "$first" = "1" ] && enable_ipv6
  build_and_sync
  verify
}

rebuild() {
  preflight
  if is_first_checkout; then
    die "no existing checkout at $REMOTE_DIR — run Deploy first"
  fi
  git_sync
  build_and_sync
}

logs() {
  say "Logs (Ctrl+C to stop)"
  printf 'Services: %s (press Enter for all)\n' "${SERVICES[*]}"
  printf 'Service: '
  local svc
  read -r svc || svc=""

  local remote
  remote="cd '$REMOTE_DIR' && docker compose logs -f --since 5m"
  [ -n "$svc" ] && remote="$remote '$svc'"

  ssh -t "${SSH_OPTS[@]}" "$REMOTE_SSH" "$remote" || true
  return 0
}

prune() {
  preflight
  say "Docker disk usage before pruning"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'docker system df'
  say "Pruning stopped containers, dangling images, unused networks, and build cache"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'docker system prune -f'
  say "Docker disk usage after pruning"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'docker system df'
}

sql() {
  say "MySQL shell as root on niblmysql (type 'exit' or Ctrl+D to leave)"
  ssh -t "${SSH_OPTS[@]}" "$REMOTE_SSH" \
    "cd '$REMOTE_DIR' && docker compose exec niblmysql sh -c 'MYSQL_PWD=\$MYSQL_ROOT_PASSWORD exec mysql -uroot --socket=/tmp/mysql.sock'" \
    || true
  return 0
}

# ---------------------------------------------------------------------------
# Diagnostics
# ---------------------------------------------------------------------------

# On the remote host, bundle every compose service's log, the Docker/container
# inventory, the host-side `logs/` directory, the on-disk botlists
# (data/niblirc), and a full MySQL dump; download it and unpack it under
# LOCAL_LOG_EXPORT_DIR/<timestamp>/ for offline review.
diagnostics() {
  preflight

  local stamp dest
  stamp="$(date +%Y%m%d-%H%M%S)"
  dest="$LOCAL_LOG_EXPORT_DIR/$stamp"
  mkdir -p "$dest"

  say "Collecting logs on $REMOTE_SSH"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' \
    "$REMOTE_DIR" "$stamp" "$DIAG_LOG_TAIL" <<'REMOTE_EOF'
set -uo pipefail
root=$1; stamp=$2; tail=$3

# Safety: never rm -rf a path that isn't a direct child of <root>/logs-export.
# Validate the two inputs that set that path, then confirm the target shape.
case "$root" in
  /*) ;;
  *) echo "ERROR: diagnostics root must be absolute: '$root'" >&2; exit 1 ;;
esac
case "$stamp" in
  ''|*[!0-9-]*|-*|*-) echo "ERROR: invalid diagnostics stamp '$stamp'" >&2; exit 1 ;;
esac

bundle="$root/logs-export/$stamp"
case "$bundle" in
  "$root/logs-export"/*) ;;
  *) echo "ERROR: refusing to touch '$bundle' (outside logs-export)" >&2; exit 1 ;;
esac

rm -rf -- "$bundle"
mkdir -p "$bundle"
cd "$root" || exit 1

# Ask compose for the service list instead of passing it through ssh, which
# re-splits its arguments on spaces.
services=$(docker compose config --services 2>/dev/null)
[ -n "$services" ] || services="niblmysql niblirc niblcouk swag meilisearch niblscheduler"

# One file per compose service: stdout+stderr, colourless, timestamped.
for svc in $services; do
  docker compose logs --no-color --timestamps --tail "$tail" "$svc" \
    >"$bundle/docker-$svc.log" 2>&1 || true
done

docker ps -a --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}' \
  >"$bundle/docker-ps.txt" 2>&1 || true
docker compose ps >"$bundle/docker-compose-ps.txt" 2>&1 || true
docker version >"$bundle/docker-version.txt" 2>&1 || true
docker inspect $(docker compose ps -aq) >"$bundle/docker-inspect.json" 2>&1 || true

# Application-written log files (per-project subdirectories).
if [ -d "$root/logs" ]; then
  cp -a "$root/logs" "$bundle/logs" || true
fi

# Botlists on disk: $root/data/niblirc is the directory bind-mounted into the
# niblirc container at /data/botlists. Included so a stale bot's MySQL/Meili
# state can be compared against the exact packlist it downloaded. They are
# absent unless keep_packlist_files is enabled or a copy was dropped here.
if [ -d "$root/data/niblirc" ]; then
  cp -a "$root/data/niblirc" "$bundle/botlists" || true
fi

# Full MySQL dump via the existing backup tool (also refreshes the 7-dump
# rotation). Fold the produced dump into the bundle; on failure record it but
# keep going so the log bundle is still produced.
if bash "$root/niblmysql/backup.sh" dump; then
  latest_db=$(ls -1t "$root/niblmysql/backup"/all-databases-*.sql.gz 2>/dev/null | head -1 || true)
  if [ -n "$latest_db" ] && [ -f "$latest_db" ]; then
    cp -a "$latest_db" "$bundle/"
    echo "ok   $(basename "$latest_db")" > "$bundle/mysql-dump.status"
  else
    echo "FAILED (backup.sh dump produced no file)" > "$bundle/mysql-dump.status"
  fi
else
  echo "FAILED (backup.sh dump exited non-zero)" > "$bundle/mysql-dump.status"
fi

tar -czf "$root/logs-export/$stamp.tgz" -C "$bundle" . || exit 1
rm -rf -- "$bundle"
ls -l "$root/logs-export/$stamp.tgz"
REMOTE_EOF

  say "Downloading bundle"
  scp -q -o BatchMode=yes -o ConnectTimeout=60 \
    "$REMOTE_SSH:$REMOTE_DIR/logs-export/$stamp.tgz" "$dest/" \
    || die "download failed"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "rm -f '$REMOTE_DIR/logs-export/$stamp.tgz'"

  tar -xzf "$dest/$stamp.tgz" -C "$dest" && rm -f "$dest/$stamp.tgz"

  say "Diagnostics exported to $dest"
  du -sh "$dest" || true
  find "$dest" -maxdepth 3 -type f -printf '  %10s  %P\n' | sort -k2 || true
}

# ---------------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------------

while :; do
  menu_title
  printf '  choose: '
  if ! IFS= read -r key; then
    printf '\n'
    break
  fi
  printf '\n'
  case "$key" in
    1) action Deploy deploy ;;
    2) action Rebuild rebuild ;;
    3) logs ;;
    4) action Diagnostics diagnostics ;;
    5) action Prune prune ;;
    6) sql ;;
    q|Q) printf '\nBye.\n'; exit 0 ;;
    '') ;;
    *) printf '  Unknown option "%s"\n' "$key" ;;
  esac
done