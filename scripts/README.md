# nibl.sh — production deploy/rebuild/logs TUI

`nibl.sh` is the production management TUI for the whole NIBL stack —
**niblmysql**, **niblirc**, **niblcouk** (nibl.co.uk), **swag** (linuxserver/swag,
the Let's Encrypt TLS gateway) and **meilisearch** — all defined in the root
`docker-compose.yml` and deployed from a single Git checkout.

Run it from `NIBL/scripts/`:

```sh
bash nibl.sh
```

It shows a small menu:

- **1 Deploy** — full pipeline: clone/pull, seed secrets + backups, enable Docker
  IPv6 (first run), build + start the stack, run migrations + Meilisearch
  backfill, then verify the site and API over HTTPS.
- **2 Rebuild** — pull the latest code, rebuild images, restart, migrate and
  re-sync Meilisearch. Skips first-time setup and verification (assumes a prior
  Deploy).
- **3 Logs** — follow live container logs (`docker compose logs -f`), optionally
  scoped to one service.
- **4 Diagnostics** — export every container's log plus the host-side `logs/`
  tree into a timestamped folder on your machine for offline review.
- **5 Prune** — reclaim disk space on the host: remove stopped containers,
  dangling images, unused networks, and the build cache (`docker system prune
  -f`; volumes and tagged images are never touched).
- **6 SQL** — open an interactive MySQL shell on `niblmysql` as `root`.

It manages the stack over SSH; nothing runs on your machine except `ssh`/`scp`
and the remote commands. It does **not** `git commit` or `git push` — you push
first, then deploy or rebuild pulls the repository down to the host.

## What it deploys where

Everything lives under **`/opt/NIBL/`** on the production host:

- The single `NIBL` Git repository is cloned (or pulled) into `/opt/NIBL`.
- `/opt/NIBL/.env` holds the production secrets (gitignored). On the **first**
  deployment it is copied up from the local repo-root `.env`; later deploys keep
  the host's copy untouched.

The three runtime projects and the TLS gateway are no longer separate repos or
separate compose files — they are the subdirectories of the one repository and
the services of the one root compose file.

## Prerequisites

**On the host:**

- Docker + Compose v2, and `curl` (for the final HTTP check).
- SSH reachability as the `REMOTE_SSH` user, with Docker permissions.
- Read access to the `NIBL` Git repository (HTTPS clone by default).

**Locally:**

- `ssh` and `scp` on `PATH`, the repo-root `.env` populated with production
  secrets, and `niblmysql/backup/*.sql.gz` dumps to restore from (the `.env` is
  copied on the first deploy; backups are uploaded when the host has none).

## Configuration

Edit these at the top of the script before the first run:

| Variable | Purpose |
|---|---|
| `REMOTE_SSH` | `user@host` or a `~/.ssh/config` alias (currently `nibl`) |
| `SSH_USE_AGENT_FORWARDING` | Forward the local `ssh-agent` (only needed for an SSH clone) |
| `REMOTE_DIR` | Remote checkout root (default `/opt/NIBL`) |
| `REPO_URL` | The `NIBL` Git remote (HTTPS read-only by default) |
| `REMOTE_BRANCH` | Production branch (default `main`; merge `develop` → `main` for releases) |
| `SITE_HOST` / `SITE_URL` / `API_URL` | Final HTTP verification targets |
| `SERVICES` | Compose services offered by **Logs** and exported by **Diagnostics** |
| `LOCAL_LOG_EXPORT_DIR` | Local destination for Diagnostics bundles (default `NIBL/logs-export/`, gitignored) |
| `DIAG_LOG_TAIL` | Lines of history exported per service (default `all`; set a number to cap very long logs) |

## Deploy — order of operations

| Step | What it does |
|---|---|
| **0 — Preflight** | Checks local `ssh`/`scp` exist and Docker is reachable on the host. |
| **1 — Remote dir** | `mkdir -p /opt/NIBL`. |
| **2 — Clone/pull the repo** | Clones `NIBL` if `.git` is missing, otherwise `git fetch` → `checkout` → `reset --hard origin/<branch>`. |
| **3 — Seed secrets & backups** | Copies the local repo-root `.env` to `/opt/NIBL/.env` (then `chmod 600`) on a fresh clone only, and uploads `niblmysql/backup/` only when the host has no backups yet (so `bootstrap.sh` can restore an empty database). Then verifies `/opt/NIBL/.env` exists and is non-empty. |
| **4 — Enable Docker IPv6 (first checkout)** | Runs `scripts/setup-docker-ipv6.sh` over `ssh -t` via `sudo` (prompts for the host password) to write `/etc/docker/daemon.json` and restart the daemon; idempotent on follow-up runs. |
| **5 — Build + start the stack** | Creates the dual-stack `my-bridge` network if missing, then `docker compose build --pull` (builds all images). Brings up `niblmysql`, waits healthy, runs `niblmysql/bootstrap.sh` (restore + provision `ooinuza`), applies migrations in a side-load container (`docker compose run --rm niblcouk php artisan migrate --force`), then `docker compose up -d --remove-orphans` for the rest (recreating any container whose image changed). Finally waits for Meilisearch and backfills the index. |
| **6 — Verify** | Lists container states, then curls `SITE_URL` and `API_URL` through swag (`--resolve` to `127.0.0.1`); requires both to return `200`. |

## Rebuild and Logs

**Rebuild** runs a subset of the Deploy path: `git pull`, then the build + start
+ migrate + Meilisearch sync (Deploy steps 2 and 5). It skips seeding
secrets/backups, Docker IPv6 setup, and the final HTTP verification, so it only
works on a host that has already been deployed.

**Logs** runs `docker compose logs -f` on the host (optionally scoped to one
service) so you can watch container output live.

## Diagnostics

**Diagnostics** collects everything needed to debug a problem offline, without
touching the running stack:

1. On the host it writes one file per compose service
   (`docker compose logs --no-color --timestamps --tail "$DIAG_LOG_TAIL"`), plus
   `docker ps -a`, `docker compose ps`, `docker version` and a full
   `docker inspect` of every container.
2. It copies the host's `logs/` tree (per-project application log files:
   `niblirc/`, `nibl.co.uk/`, `nibl.scheduler/`, `niblmysql/`, `swag/`).
3. Everything is tarred on the host, downloaded with `scp`, and unpacked into
   `LOCAL_LOG_EXPORT_DIR/<timestamp>/`; the remote tarball is then removed.

Bundles contain production log lines, so `logs-export/` is gitignored — review
them locally and never commit or paste them wholesale.

## Prune

**Prune** prints `docker system df` on the host, runs `docker system prune -f`
(removes stopped containers, dangling images, unused networks, and the build
cache — never volumes, tagged images, or running containers), then prints
`docker system df` again so you can see what was reclaimed. It targets the
production host only; the local development host is governed by
`.github/instructions/DOCKER.md`, not by this script.

## SQL

**SQL** opens an interactive MySQL client inside the `niblmysql` container as the
`root` account (no default schema), for ad-hoc queries over the production
database. Exit with `exit` or `Ctrl+D`.

## Idempotency & safety notes

- `git reset --hard origin/<branch>` makes the remote working tree exactly match
  the branch; any uncommitted changes on the host are discarded. Never edit code
  directly on the production host.
- Gitignored live state (MySQL `data/`, swag `keys/`/`log/`/`etc/letsencrypt/`)
  is never in the repository, so it survives `git pull` untouched.
- `.env` is also gitignored and is written to the host only on the first
  checkout, so later deploys never overwrite the host's production secrets.
- `setup-docker-ipv6.sh` is run (via `sudo`) only on a first checkout; it writes
  `/etc/docker/daemon.json` only when that file is missing or empty and restarts
  the Docker daemon, so an existing daemon.json is never clobbered.
- `bootstrap.sh` restores the newest `niblmysql/backup/` dump only if `ooinuza`
  is empty, then provisions the `ooinuza` user idempotently.
- `php artisan migrate --force` is idempotent (Laravel records applied migrations
  in `ooinuza.migrations`); guarded migrations adopt an existing DB without
  re-creating anything.