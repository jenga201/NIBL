#!/usr/bin/env bash
#
# deploy.sh — deploy the complete NIBL stack (niblmysql, niblirc, niblcouk,
# swag) from a single Git checkout on the production host.
#
# Run from NIBL/scripts/ on your local machine:
#     bash deploy.sh
#
# The whole stack is defined in the root docker-compose.yml and sequenced by
# `depends_on`, so a single `docker compose up` starts everything. This script
# adds the host orchestration around it: clone/pull the repo, seed secrets,
# apply migrations, and verify over HTTPS.
#
# What it does (does NOT git commit/push — you do that first and push):
#   1. Preflight: ssh/scp present locally, Docker reachable on the host.
#   2. Ensure /opt/NIBL exists.
#   3. Clone (or pull) the single NIBL repository into /opt/NIBL.
#   4. Seed .env (first checkout) and MySQL backups (if missing); enable Docker
#      IPv6 (first checkout, via sudo); create the my-bridge network.
#   5. docker compose build + up for the whole stack.
#   6. Wait for niblmysql healthy, bootstrap the app user + run Laravel migrations.
#   7. Verify: container states + an HTTP check through swag.
#
# Prerequisites:
#   - Locally: the gitignored .env at the repo root (first deploy only), and
#     niblmysql/backup/ dumps (uploaded when the host has no backups yet).
#   - On the host: Docker + Compose v2 and curl; SSH reachability as REMOTE_SSH
#     with Docker permissions; read access to the NIBL Git repository (HTTPS).
set -euo pipefail

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

# HTTP verification targets (proxied through swag).
SITE_HOST="www.nibl.co.uk"
SITE_URL="https://www.nibl.co.uk"
API_URL="https://www.nibl.co.uk/api/bots"

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

[ -n "$REMOTE_SSH" ] || { echo "ERROR: set REMOTE_SSH at the top of $0" >&2; exit 1; }

SSH_OPTS=(-o BatchMode=yes -o ConnectTimeout=15)
[ "$SSH_USE_AGENT_FORWARDING" = "true" ] && SSH_OPTS+=(-A)

say()  { printf '\n\033[1;36m==>\033[0m %s\n' "$*"; }
fail() { printf '\n\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Step 0 — preflight
# ---------------------------------------------------------------------------

say "Preflight"
command -v ssh >/dev/null || fail "ssh not found"
command -v scp >/dev/null || fail "scp not found"
ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'docker version >/dev/null 2>&1' \
  || fail "docker is not reachable on $REMOTE_SSH"

# ---------------------------------------------------------------------------
# Step 1 — remote dir
# ---------------------------------------------------------------------------

say "Remote directory"
ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' "$REMOTE_DIR" <<'REMOTE_EOF'
set -euo pipefail
root=$1
mkdir -p "$root"
REMOTE_EOF

# ---------------------------------------------------------------------------
# Step 2 — clone/pull the single repository
# ---------------------------------------------------------------------------

# A fresh checkout is one where the repository does not yet exist on the host.
# The local .env is only copied up in that case (and never overwritten after).
if ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "[ -d '$REMOTE_DIR/.git' ]"; then
  FIRST_CHECKOUT=false
else
  FIRST_CHECKOUT=true
fi

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

# ---------------------------------------------------------------------------
# Step 3 — seed secrets and backups
# ---------------------------------------------------------------------------

# The .env is copied only on a fresh checkout; afterwards the host's copy wins.
if [ "$FIRST_CHECKOUT" = "true" ]; then
  say "First checkout — copying local .env to the host"
  [ -s "$LOCAL_ENV_FILE" ] || fail "local .env is missing or empty: $LOCAL_ENV_FILE"
  scp -q -o BatchMode=yes -o ConnectTimeout=15 "$LOCAL_ENV_FILE" "$REMOTE_SSH:$REMOTE_ENV_FILE"
  ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "chmod 600 '$REMOTE_ENV_FILE'"
else
  say "Existing checkout — keeping the host's .env"
fi

# The .env must exist before docker compose runs (just copied, or pre-existing).
ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" "[ -s '$REMOTE_ENV_FILE' ]" \
  || fail "$REMOTE_ENV_FILE is missing or empty"

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

# ---------------------------------------------------------------------------
# Step 4 — enable Docker IPv6 on a first checkout (sudo)
# ---------------------------------------------------------------------------

if [ "$FIRST_CHECKOUT" = "true" ]; then
  say "Enabling Docker IPv6 on the host (enter the sudo password when prompted)"
  ssh -t "${SSH_OPTS[@]}" "$REMOTE_SSH" \
    "sudo bash '$REMOTE_DIR/scripts/setup-docker-ipv6.sh'" \
    || fail "Docker IPv6 setup failed"
fi

# ---------------------------------------------------------------------------
# Step 5 — build + start the whole stack
# ---------------------------------------------------------------------------

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
# `up -d` starts the whole stack; depends_on (niblmysql healthy) sequences it.
# The app DB user and pending schema migrations are applied afterwards by
# niblmysql/bootstrap.sh.
docker compose up -d --remove-orphans

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

# Bootstrap (restore backup if empty + provision the app user), then apply the
# schema via Laravel migrations (nibl.co.uk/database/migrations), then backfill
# the Meilisearch index (later changes are picked up by the minute scheduler).
bash niblmysql/bootstrap.sh
docker compose run --rm niblcouk php artisan migrate --force
docker compose run --rm niblcouk php artisan meilisearch:sync

# Let the app containers come up, then report their states.
sleep 10
for c in niblirc niblcouk swag; do
  state=$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null || echo missing)
  echo "$c: $state"
done
docker ps --format '  {{.Names}}\t{{.Status}}'
REMOTE_EOF

# ---------------------------------------------------------------------------
# Step 6 — verify
# ---------------------------------------------------------------------------

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

say "Done"
