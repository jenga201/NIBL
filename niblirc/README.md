# Intro
This is the ooinuza project for #nibl

# Setup
Install Mysql Docker Container

Compile and Run Ooinuza Docker Container

## Required Software
	Docker

## Database
#### Create mysql container
```
docker network create -d bridge my-bridge
docker pull mysql:8
docker run --network my-bridge --name niblmysql -e MYSQL_ROOT_PASSWORD={somerootpassword} -d mysql
docker exec -i niblmysql mysql -uroot -p{somerootpassword} < db/ooinuza.sql
```

#### Create mysql user
```
docker exec -it niblmysql bash
mysql -u root -p{somerootpassword}
CREATE USER 'ooinuza'@'172.18.0.%' IDENTIFIED BY 'niblempire';
GRANT ALL PRIVILEGES ON 'ooinuza'.* TO 'ooinuza'@'172.18.0.%';
FLUSH PRIVILEGES;
```
## Run with Docker Compose
The `ooinuza` service is defined in the top-level `NIBL/docker-compose.yml`; there
is no per-project compose file (`niblmysql` starts first via `depends_on`). Run
from the repo root:
```
cp .env.example .env   # set IRC_BOT_NAME, IRC_SERVER, IRC_PORT, IRC_DCC_PORTS, etc.
docker compose up -d --build
```
`niblirc/config/` and `niblirc/botlists/` are bind-mounted (relative to the repo
root) to `/config` and `/data/botlists`. Ports 46571-46573 are published for DCC.

Configuration: IRC identity, server, DCC ports and database connection are provided as environment variables (mapped in `docker-compose.yml`); `config/config.txt` keeps only static, non-secret settings. The DCC public address (`public_ip`) is optional — left blank, the bot resolves it from `https://ifconfig.me/ip` at startup. Set `IRC_PUBLIC_IP` to pin it.

## Tests
Unit tests (JUnit 5) run inside the Docker build stage and gate the image: if a test fails, the build fails and no new image is produced.
```
docker compose build    # runs mvn -B clean package -> surefire
```
The tests are non-destructive: they run against an in-memory H2 database (MySQL compatibility mode) seeded from `src/test/resources/schema.sql` (derived from the canonical `niblmysql` schema) with fake bot/pack data - they never touch the production MySQL database or IRC.

Restarting a container does NOT re-run the tests; they only run when the image is rebuilt (i.e. after `pom.xml` or `src/` changes):
```
docker compose restart  # reuses the existing image, no tests
docker compose up -d    # reuses the existing image unless --build is given
```

## Run App (manual)
The public IP is needed to enable dcc chat

Only one range of ports is allowed for dcc chat and must match the `DCC_PORTS` environment value (todo fix this)

```
docker run -d \
--net='my-bridge' \
--name=niblirc \
-p 46571-46573:46571-46573 \
-e BOT_NAME=dockerizedBot \
-e BOT_PASS=password \
-e SERVER=irc.rizon.net \
-e PORT=9999 \
-e DCC_PORTS=46571,46572,46573 \
-e DB=mysql \
-e DB_USER=ooinuza \
-e DB_PASS=niblempire \
-e DB_SERVER=niblmysql \
-e DB_PORT=3306 \
-e DB_NAME=ooinuza \
-v "$(pwd)/config":/config:rw \
-v "$(pwd)/botlists":/data/botlists:rw \
ooinuza
```

The public IP is resolved automatically from ifconfig.me; pass `-e PUBLIC_IP={publicIp}` to pin it.
