# niblmysql

MySQL 8.4 back end shared by `niblirc` (writer) and `nibl.co.uk` (reader).

- `bootstrap.sh` - restore a backup into an empty DB, then provision the `ooinuza` user (`caching_sha2_password`).
- `backup.sh` - dump/restore the databases through the container.
- `data/` - MySQL datadir (gitignored); `backup/` - retained dumps.

The **schema is owned by `nibl.co.uk/database/migrations`** (`php artisan migrate --force`),
not by any SQL file here.

## Run

Defined in the top-level `NIBL/docker-compose.yml` as the `niblmysql` service;
there is no per-project compose file. Run from the repo root:

```bash
docker network create -d bridge my-bridge   # once per host
cp .env.example .env                         # then set MYSQL_ROOT_PASSWORD, SQL_*
docker compose up -d
```

Data persists in `data/niblmysql/` (gitignored); the MySQL error log is written to
`logs/niblmysql/`. 3306/33060 stay on the internal network; the admin port is
published loopback-only at `${NIBLMYSQL_BIND:-127.0.0.1}:${NIBLMYSQL_HOST_PORT:-3306}`.

## Schema & user

```bash
bash bootstrap.sh                                               # restore backup if empty + provision user
docker compose run --rm niblcouk php artisan migrate --force    # apply migrations
```

## Backups

`backup.sh` dumps and restores through the container; credentials never touch the host
(mysql/mysqldump run inside the container using its `MYSQL_ROOT_PASSWORD`).

```bash
./backup.sh            # interactive menu
./backup.sh dump       # all databases -> backup/all-databases-<timestamp>.sql.gz (keeps newest 7)
./backup.sh restore    # interactive picker; or: ./backup.sh restore backup/<file>.sql.gz
./backup.sh list       # list available backups
```

Add `-f`/`--force` to skip confirmation prompts.

## Notes

- The `ooinuza` user is created/kept current by `bootstrap.sh` (least-privilege grants on `ooinuza.*` only); apps never use root.
- Start this stack before `niblirc` and `nibl.co.uk` (compose `depends_on` enforces this).
