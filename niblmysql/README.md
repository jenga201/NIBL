# niblmysql

Common MySQL 8 back end shared by `niblirc`, `niblapi`, and `nibl.co.uk`.

- `schema.sql` - database schema
- `data.sql` - seed/exported data
- `docker-compose.yml` - runs the `niblmysql` container

## Run

Defined in the top-level `NIBL/docker-compose.yml` as the `niblmysql` service;
there is no per-project compose file. Run from the repo root:

```bash
docker network create -d bridge my-bridge   # once per host
cp .env.example .env                         # then set MYSQL_ROOT_PASSWORD
docker compose up -d
```

Data persists in `data/niblmysql/` (repo-relative, gitignored) via the
`./data/niblmysql:/var/lib/mysql` bind mount, and the MySQL error log is written
to `logs/niblmysql/`. The container exposes 3306/33060 to the `my-bridge`
network; the MySQL admin port is published to the host at
`${NIBLMYSQL_BIND:-127.0.0.1}:${NIBLMYSQL_HOST_PORT:-3306}`.

## Import schema and data

The compose file sets `MYSQL_DATABASE: ooinuza`, so the database is created automatically on first init. Import with `./backup.sh init`, or manually:

```bash
docker exec -i niblmysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < schema.sql
docker exec -i niblmysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD"' < data.sql
```

## Backups

`backup.sh` dumps and restores through the container; credentials never touch the host
(mysql/mysqldump run inside the container using its `MYSQL_ROOT_PASSWORD`).

```bash
./backup.sh            # interactive menu
./backup.sh dump       # all databases -> backup/all-databases-<timestamp>.sql.gz (keeps newest 7)
./backup.sh restore    # interactive picker; or: ./backup.sh restore backup/<file>.sql.gz
./backup.sh init       # import schema.sql + data.sql into ooinuza
./backup.sh list       # list available backups
```

Add `-f`/`--force` to skip confirmation prompts.

## Notes

- Create per-application users (for example `ooinuza`) with least-privilege grants; do not use root from apps.
- Start this stack before `niblirc`, `niblapi`, and `nibl.co.uk`.
