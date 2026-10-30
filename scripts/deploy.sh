#!/usr/bin/env bash
#
# deploy.sh — deploy the complete NIBL stack (niblmysql, ooinuza, niblcouk,
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
#   1. Preflight: ssh present locally, Docker reachable on the host.
#   2. Ensure /opt/NIBL, the my-bridge network, and /opt/NIBL/.env exist.
#   3. Clone (or pull) the single NIBL repository into /opt/NIBL.
#   4. docker compose build + up for the whole stack.
#   5. Wait for niblmysql healthy, clean up one-shot sidecars, run migrations.
#   6. Verify: container states + an HTTP check through swag.
#
# Prerequisites on the host:
#   - Docker + Compose v2, and curl for the verification check.
#   - /opt/NIBL/.env already populated with production secrets (copied from
#     .env.example — it is gitignored and never deployed by this script).
#   - SSH reachability as REMOTE_SSH with Docker permissions (docker group).
#   - Read access to the NIBL Git repository (HTTPS clone by default).
set -euo pipefail

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

# Remote SSH target: "user@host" or an alias from ~/.ssh/config.
REMOTE_SSH="nibl2"

# Forward the local ssh-agent across the SSH connection. Only needed if the host
# clones via SSH — the default HTTPS clone needs no agent.
SSH_USE_AGENT_FORWARDING="false"

# Remote checkout root and Git details for the single combined repository.
REMOTE_DIR="/opt/NIBL"
REPO_URL="https://github.com/jenga201/NIBL.git"
REMOTE_BRANCH="main"   # production branch; merge develop -> main for releases

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
ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'docker version >/dev/null 2>&1' \
  || fail "docker is not reachable on $REMOTE_SSH"

# ---------------------------------------------------------------------------
# Step 1 — remote dir + network + secrets
# ---------------------------------------------------------------------------

say "Remote directory, my-bridge network, and secrets"
ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' "$REMOTE_DIR" <<'REMOTE_EOF'
set -euo pipefail
root=$1
mkdir -p "$root"
docker network ls --format '{{.Name}}' | grep -qx my-bridge \
  || docker network create -d bridge my-bridge
[ -s "$root/.env" ] || { echo "ERROR: $root/.env is missing or empty — create it from .env.example first."; exit 1; }
REMOTE_EOF

# ---------------------------------------------------------------------------
# Step 2 — clone/pull the single repository
# ---------------------------------------------------------------------------

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
# Step 3 — build + start the whole stack
# ---------------------------------------------------------------------------

say "Build + start the stack (niblmysql -> ooinuza + niblcouk -> swag)"
ssh "${SSH_OPTS[@]}" "$REMOTE_SSH" 'bash -s' "$REMOTE_DIR" <<'REMOTE_EOF'
set -euo pipefail
cd "$1"
docker compose build --pull
# `up -d` starts the whole stack; depends_on (niblmysql healthy) sequences it,
# and the one-shot mysql-init sidecar (restart: "no") provisions the app DB user
# and exits 0. Deliberately NOT `up --wait`: --wait hangs/fails on that sidecar.
docker compose up -d

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

# Remove any one-shot sidecar containers (compose `restart: "no"`) — e.g.
# mysql-init, which provisions the app DB user and exits 0. Long-running
# services all use `restart: unless-stopped` and are left alone.
cleanup_sidecars() {
  local cid policy state code
  for cid in $(docker compose ps -a -q 2>/dev/null); do
    policy=$(docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' "$cid" 2>/dev/null || true)
    [ "$policy" = "no" ] || continue
    state="running"
    for _ in $(seq 1 24); do
      state=$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null || echo missing)
      case "$state" in exited|missing) break ;; esac
      sleep 5
    done
    code=$(docker inspect -f '{{.State.ExitCode}}' "$cid" 2>/dev/null || echo -1)
    docker rm -f "$cid" >/dev/null 2>&1 || true
    if [ "$state" = "exited" ] && [ "$code" != "0" ]; then
      echo "ERROR: one-shot sidecar $cid exited with code $code" >&2
      exit 1
    fi
    echo "cleaned up one-shot sidecar $cid"
  done
}
cleanup_sidecars

# Apply pending schema migrations (idempotent; see niblmysql/migrate.sh).
bash niblmysql/migrate.sh

# Let the app containers come up, then report their states.
sleep 10
for c in ooinuza niblcouk swag; do
  state=$(docker inspect -f '{{.State.Status}}' "$c" 2>/dev/null || echo missing)
  echo "$c: $state"
done
docker ps --format '  {{.Names}}\t{{.Status}}'
REMOTE_EOF

# ---------------------------------------------------------------------------
# Step 4 — verify
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