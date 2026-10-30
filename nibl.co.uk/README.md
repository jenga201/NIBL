# nibl.co.uk

The nibl.co.uk website: Laravel 13 (PHP 8.5) on Apache, with Blade templates and a Tailwind CSS 4 + Vite frontend pipeline. Everything builds and runs in Docker — no host PHP, Composer, or Node required.

## Requirements

* Docker with the compose plugin
* The top-level `NIBL/.env` (copied from `NIBL/.env.example`) — see that file for the `WEB_*` and shared `SQL_*` variables this project uses
* The `niblmysql` stack running (start it first — the site reads bots/packs from the `ooinuza` database)

## Build & run

There is no per-project compose file — the `niblcouk` service is defined in the
top-level `NIBL/docker-compose.yml`. Run from the repo root:

```
docker compose up -d --build
```

Runs the `niblcouk` container (Apache, port `${WEB_HTTP_PORT:-8080}` → 80) on the external `my-bridge` network, with runtime data in `data/nibl.co.uk/` mounted at `/var/www/html/var` and daily logs in `logs/nibl.co.uk/` mounted at `/var/www/html/var/logs`. Laravel's storage path is `var/`.

The Dockerfile is the only build pipeline:

* **Node stage** — `npm ci` then `npm run build` compiles `resources/css` + `resources/js` (Tailwind 4 via `@tailwindcss/vite`) into `public/build/` with a Vite manifest. Built assets are **not** committed; they exist only inside the image.
* **PHP stage** — `composer install --no-dev -a` installs dependencies with an optimized, authoritative autoloader and runs `artisan package:discover`.

On every container start, `etc/docker/start-apache` creates the `var/framework/*` + `var/logs` directories, clears compiled views from previous images (OPcache runs with `validate_timestamps=0`), runs `artisan config:cache` and `artisan view:cache`, and chowns everything to `www-data` (the host mount may be root-owned).

## Configuration

All runtime configuration is injected as environment variables by the top-level `NIBL/docker-compose.yml` (the image contains no `.env` file); values come from the shared `NIBL/.env` (templated by `NIBL/.env.example`):

* `WEB_APP_KEY` (**required**) — Laravel application key, e.g. `base64:<openssl rand -base64 32 output>`.
* `SQL_USER` / `SQL_PASSWORD` (**required**) — mapped to `DB_USERNAME`/`DB_PASSWORD`; DB host/database default to `SQL_HOST`/`SQL_DATABASE` (`niblmysql`/`ooinuza`).
* Optional: `WEB_APP_URL`, `WEB_DATA_CACHE` (`apcu`, `redis` or `array`), `WEB_LOG_LEVEL` (default `info`).

App-specific settings live in `config/api.php`; sessions use the `file` driver with the legacy `nibl-web` cookie name.

## JSON API

The site serves the NIBL bot/pack API directly from the `ooinuza` database — the old `api.nibl.co.uk` (`niblapi`) service is deprecated and no longer called. Routes live in `routes/api.php` (mounted under the `/api` prefix) and return standard Laravel JSON: `{"data": ...}` resources, plus `links` and paginator metadata under `meta` (`current_page`, `per_page`, `total`, ...) on the `/page` endpoints.

| Route | Purpose |
|---|---|
| `GET /api/bots` | All active bots (with pack counts) |
| `GET /api/bots/{botId}` | A single bot |
| `GET /api/packs/{botId}` | All packs of a bot |
| `GET /api/packs/{botId}/page` | Paginated bot packs (`page`, `size`, `sort`, `direction`, `episode_number`) |
| `GET /api/latest` | Latest packs, newest first (`size`, default 50) |
| `GET /api/search` | Search all packs (`query` required, `episode_number` optional) |
| `GET /api/search/page` | Paginated search |
| `GET /api/search/{botId}` | Search within one bot |
| `GET /api/search/{botId}/page` | Paginated search within one bot |

`sort` accepts `name`, `number`, `size`, `sizekbits`, `episode_number`, `last_modified`, `bot_id` and `id`; `direction` accepts `asc`/`desc`. Pagination is 1-based (`page` default 1, `size` default 50, max 1000). Search is case-insensitive and every whitespace-separated term must appear in the pack name, in order (same semantics as the old API).

## Updating dependencies

After changing `composer.json` or `package.json`, regenerate the lock files with:

```
scripts/update-locks.sh
```

It runs `composer update` and `npm install --package-lock-only` in throw-away Docker containers (no host toolchain), then rebuild with `docker compose build`.

## Development

1. Frontend: edit `resources/js/app.js`, `resources/css/app.css`, or the Blade views in `resources/views/`, then `docker compose up -d --build` — assets recompile during the image build.
2. Routes are in `routes/web.php` (HTML pages) and `routes/api.php` (`/api` JSON API); controllers in `app/Http/Controllers/`. Bots and packs are read through the Eloquent models in `app/Models/`; domain logic (`app/Domain/`, `app/Infrastructure/`) is wired up in `app/Providers/AppServiceProvider.php`.
3. Health check: `GET /up`.
4. Code quality: `composer format` (Laravel Pint) and `composer psalm` require the dev dependencies that the runtime image omits (`--no-dev`). Run them in a one-off container when needed:

   ```
   docker run --rm -v "$PWD":/app -w /app composer:2 sh -c "composer install && composer format && composer psalm"
   ```
