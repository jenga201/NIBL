#!/usr/bin/env bash
#
# setup-docker-ipv6.sh — enable Docker IPv6 so the compose `[::]` port publishes
# work on a dual-stack host. Idempotent: safe to re-run.
#
# Run as root (deploy.sh invokes it via sudo on first checkout):
#   sudo bash /opt/NIBL/scripts/setup-docker-ipv6.sh
set -euo pipefail

DAEMON_JSON=/etc/docker/daemon.json

if [ -s "$DAEMON_JSON" ] && grep -q '"ipv6"[[:space:]]*:[[:space:]]*true' "$DAEMON_JSON"; then
  echo "Docker IPv6 is already enabled — nothing to do."
  exit 0
fi

# Treat a missing, empty, or '{}' daemon.json as blank; refuse to clobber a real
# configuration file.
content=$(cat "$DAEMON_JSON" 2>/dev/null || true)
if [ -n "$(printf '%s' "$content" | tr -d '[:space:]{},')" ]; then
  echo "Refusing to overwrite non-empty $DAEMON_JSON. Add these keys and restart Docker:" >&2
  printf '%s\n' '{ "ipv6": true, "fixed-cidr-v6": "fd00:cc00::/80" }' >&2
  exit 1
fi

cat > "$DAEMON_JSON" <<'EOF'
{
  "ipv6": true,
  "fixed-cidr-v6": "fd00:cc00::/80"
}
EOF

systemctl restart docker
echo "Docker daemon updated and restarted with IPv6 enabled."
echo "Bridge networks created from now on will be dual-stack."