# NIBL

Monorepo for the NIBL stack, combining the three previously separate projects
into a single Git repository deployed as one Docker Compose stack:

| Project | Directory | Service | Purpose |
|---|---|---|---|
| nibl.co.uk | `nibl.co.uk/` | `niblcouk` | Public website — Laravel 13 (PHP 8.5) on Apache, Tailwind + Vite frontend. |
| niblirc | `niblirc/` | `niblirc` | "niblirc" IRC bot for `#nibl`; collects XDCC pack listings into MySQL and handles DCC. Java 21 / Maven / PircBotX. |
| niblmysql | `niblmysql/` | `niblmysql` | Common MySQL 8 back end shared by the two apps above. |
| swag | `data/swag/` | `swag` | linuxserver/swag — Let's Encrypt TLS reverse proxy for `nibl.co.uk`. |

Everything is orchestrated by the single **`docker-compose.yml`** at the repo
root and configured by a single **`.env`** (copy `.env.example`).

## Requirements

- Docker with the Compose v2 plugin, on the deployment host.
- A single `.env` file at the repo root (see below).
- The external `my-bridge` network, created once per host.

## Quick start

```bash
# 1. Create the shared network (once per host)
docker network create -d bridge my-bridge

# 2. Create and populate .env from the template
cp .env.example .env
chmod 600 .env
# edit .env and fill in the real values (SQL_*, IRC_*, WEB_*, SWAG_*)

# 3. Build and start the whole stack
docker compose up -d --build
```

`docker compose up` starts everything in dependency order via `depends_on`:
`niblmysql` (waits healthy) → `niblirc` + `niblcouk` → `swag`. The one-shot
`mysql-init` sidecar provisions the application DB user on every start and exits.

## Configuration

All runtime configuration lives in the top-level `.env` (gitignored), templated
by **`.env.example`** — every variable is documented and grouped by subsystem:

- `SQL_*` — shared MySQL credentials and connection coordinates (database is `ooinuza`).
- `MYSQL_ROOT_PASSWORD`, `NIBLMYSQL_*` — MySQL container and host binding.
- `IRC_*` — niblirc identity, server, and DCC settings.
- `WEB_*` — nibl.co.uk Laravel app settings.
- `SWAG_*` — swag / Let's Encrypt reverse-proxy settings.

Compose interpolates these into each service; no `.env` is baked into any image
and no per-project `.env` files exist anymore.

## Layout

```
NIBL/
├── .env               # secrets (gitignored — never committed)
├── .env.example       # documented template for .env
├── .gitignore         # single consolidated ignore file
├── docker-compose.yml # the whole stack
├── nibl.co.uk/        # Laravel website (Dockerfile builds PHP + Node image)
├── niblirc/           # Java IRC bot (Dockerfile builds the fat jar)
├── niblmysql/         # MySQL schema, migrations, and scripts
├── data/              # runtime host data (gitignored), one subdir per project
│   ├── niblmysql/     #   MySQL datadir
│   ├── nibl.co.uk/    #   Laravel var/ (cache, sessions, app data)
│   ├── niblirc/       #   bot lists
│   └── swag/          #   swag /config (committed proxy config + live certs)
├── logs/              # application logs (gitignored), one subdir per project
│   ├── niblmysql/     #   MySQL error log
│   ├── nibl.co.uk/    #   Laravel daily logs
│   ├── niblirc/       #   bot rolling log
│   └── swag/          #   nginx/php/fail2ban logs
└── scripts/           # deploy.sh (single-command production deploy)
```

All runtime data and application logs live under the top-level `data/` and
`logs/` directories (relative to the repo root) and are gitignored, so the whole
stack is portable and self-contained. The exception is the swag proxy config that
lives under `data/swag/` (nginx proxy-confs, site-confs): it is committed so TLS
routing is versioned.

## Deploying to production

```bash
bash scripts/deploy.sh
```

See [`scripts/README.md`](scripts/README.md) for the full procedure: SSH to the
host, `git pull` this repository, build + `docker compose up`, migrations, and an
HTTP verification check through swag.

## Sub-projects

- [`nibl.co.uk/README.md`](nibl.co.uk/README.md) — website, JSON API, build & dev details.
- [`niblirc/README.md`](niblirc/README.md) — IRC bot and its tests.
- [`niblmysql/README.md`](niblmysql/README.md) — database, schema import, backups.