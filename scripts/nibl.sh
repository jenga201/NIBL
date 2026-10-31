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
SERVICES=(niblmysql niblirc niblcouk swag meilisearch)

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

docker compose build --pull

# Bring MySQL up first, then provision the app user (bootstrap.sh) BEFORE
# starting niblcouk: its entrypoint runs `php artisan migrate` on startup and
# needs the user to exist.
docker compose up -d niblmysql

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

bash niblmysql/bootstrap.sh

# Start the rest of the stack. niblcouk's start-apache applies pending
# migrations before Apache serves, so new migrations run automatically on every
# deploy/rebuild.
docker compose up -d --remove-orphans

# Wait for niblcouk to become healthy (i.e. its entrypoint has run migrations
# and Apache is up) before the Meilisearch backfill.
status="none"
for _ in $(seq 1 24); do
  status=$(docker inspect -f '{{.State.Health.Status}}' niblcouk 2>/dev/null || echo none)
  if [ "$status" = "healthy" ]; then
    break
  fi
  sleep 5
done
echo "niblcouk: $status"

# Backfill the Meilisearch index (later changes are picked up by the scheduler).
docker compose run --rm niblcouk php artisan meilisearch:sync

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
  remote="cd '$REMOTE_DIR' && docker compose logs -f"
  [ -n "$svc" ] && remote="$remote '$svc'"

  ssh -t "${SSH_OPTS[@]}" "$REMOTE_SSH" "$remote" || true
  return 0
}

# ---------------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------------

while :; do
  menu_title
  printf '  choose: '
  read -r -n 1 key || { printf '\n'; break; }
  printf '\n'
  case "$key" in
    1) action Deploy deploy ;;
    2) action Rebuild rebuild ;;
    3) logs ;;
    q|Q) printf '\nBye.\n'; exit 0 ;;
    *) printf '  Unknown option "%s"\n' "$key" ;;
  esac
done