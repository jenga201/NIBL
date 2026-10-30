# Deploy script (`deploy.sh`)

`deploy.sh` is the single-command production deployer for the whole NIBL stack —
**niblmysql**, **niblirc**, **niblcouk** (nibl.co.uk) and
**swag** (linuxserver/swag, the Let's Encrypt TLS gateway) — all defined in the
root `docker-compose.yml` and deployed from a single Git checkout.

Run it from `NIBL/scripts/`:

```sh
bash deploy.sh
```

It deploys over SSH; nothing runs on your machine except `ssh` and the remote
commands. It does **not** `git commit` or `git push` — you push first, then this
pulls the repository down to the host.

## What it deploys where

Everything lives under **`/opt/NIBL/`** on the production host:

- The single `NIBL` Git repository is cloned (or pulled) into `/opt/NIBL`.
- `/opt/NIBL/.env` holds the production secrets (gitignored — created on the host
  from `.env.example`, never deployed by this script).

The three runtime projects and the TLS gateway are no longer separate repos or
separate compose files — they are the subdirectories of the one repository and
the services of the one root compose file.

## Prerequisites

**On the host:**

- Docker + Compose v2, and `curl` (for the final HTTP check).
- `/opt/NIBL/.env` populated with production secrets (the script aborts if it is
  missing or empty).
- SSH reachability as the `REMOTE_SSH` user, with Docker permissions.
- Read access to the `NIBL` Git repository (HTTPS clone by default).

**Locally:** `ssh` on `PATH`.

## Configuration

Edit these at the top of the script before the first run:

| Variable | Purpose |
|---|---|
| `REMOTE_SSH` | `user@host` or a `~/.ssh/config` alias (currently `nibl2`) |
| `SSH_USE_AGENT_FORWARDING` | Forward the local `ssh-agent` (only needed for an SSH clone) |
| `REMOTE_DIR` | Remote checkout root (default `/opt/NIBL`) |
| `REPO_URL` | The `NIBL` Git remote (HTTPS read-only by default) |
| `REMOTE_BRANCH` | Production branch (default `main`; merge `develop` → `main` for releases) |
| `SITE_HOST` / `SITE_URL` / `API_URL` | Final HTTP verification targets |

## Order of operations

| Step | What it does |
|---|---|
| **0 — Preflight** | Checks local `ssh` exists and Docker is reachable on the host. |
| **1 — Remote dir + network + secrets** | `mkdir -p /opt/NIBL`; creates the `my-bridge` network if missing; verifies `/opt/NIBL/.env` exists and is non-empty. |
| **2 — Clone/pull the repo** | Clones `NIBL` if `.git` is missing, otherwise `git fetch` → `checkout` → `reset --hard origin/<branch>`. |
| **3 — Build + start the stack** | `docker compose build --pull` then `up -d`. `depends_on` sequences the services (MySQL healthy → web/irc → swag); the one-shot `mysql-init` sidecar provisions the app DB user and exits. Waits for `niblmysql` healthy, removes the one-shot sidecar, then runs `niblmysql/migrate.sh`. |
| **4 — Verify** | Lists container states, then curls `SITE_URL` and `API_URL` through swag (`--resolve` to `127.0.0.1`); requires both to return `200`. |

## Idempotency & safety notes

- `git reset --hard origin/<branch>` makes the remote working tree exactly match
  the branch; any uncommitted changes on the host are discarded. Never edit code
  directly on the production host.
- Gitignored live state (MySQL `data/`, swag `keys/`/`log/`/`etc/letsencrypt/`,
  and `.env`) is never in the repository, so it survives `git pull` untouched.
- `migrate.sh` is idempotent (tracks applied filenames in
  `ooinuza.schema_migrations`) and restores the newest `niblmysql/backup/`
  dump only if `ooinuza` is empty.
- The one-shot `mysql-init` sidecar is removed after it exits 0; a non-zero exit
  aborts the deploy. All long-running services use `restart: unless-stopped`.