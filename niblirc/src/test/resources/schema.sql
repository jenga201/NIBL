-- In-memory H2 schema for the niblirc unit tests.
--
-- Derived from the canonical NIBL schema (NIBL/niblmysql/schema.sql) and the
-- DDL in UpdatePackListDAO.createTables(). MySQL-dump-only clauses (ENGINE,
-- CHARSET, COLLATE, KEY index definitions, AUTO_INCREMENT counters) are
-- stripped so H2 in MySQL compatibility mode accepts the file.
--
-- Only lookup tables are seeded here; test classes insert their own fake
-- bots/packs via com.nibl.bot.testing.TestBot.

CREATE TABLE IF NOT EXISTS updatepacklist_status (
  id INT NOT NULL,
  description VARCHAR(20) NOT NULL,
  PRIMARY KEY (id)
);

INSERT INTO updatepacklist_status (id, description) VALUES (1, 'ONLINE');
INSERT INTO updatepacklist_status (id, description) VALUES (0, 'OFFLINE');

CREATE TABLE IF NOT EXISTS updatepacklist_bots (
  id INT NOT NULL AUTO_INCREMENT,
  name VARCHAR(40) NOT NULL,
  url VARCHAR(200) NOT NULL,
  type VARCHAR(10) NOT NULL,
  owner VARCHAR(255) DEFAULT NULL,
  status_id INT NOT NULL,
  last_seen DATETIME NOT NULL,
  last_processed DATETIME NOT NULL,
  informative TINYINT NOT NULL,
  batchenable TINYINT NOT NULL DEFAULT 1,
  external TINYINT NOT NULL DEFAULT 0,
  parser_id INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  CONSTRAINT updatepacklist_bots_name UNIQUE (name),
  CONSTRAINT updatepacklist_bots_ibfk_1 FOREIGN KEY (status_id) REFERENCES updatepacklist_status (id)
);

-- The canonical schema has no UNIQUE (bot_id, number), but the DAO DDL does
-- and the ON DUPLICATE KEY UPDATE upsert in insertAndUpdateListFor relies on it.
CREATE TABLE IF NOT EXISTS updatepacklist_packs (
  id BIGINT NOT NULL AUTO_INCREMENT,
  bot_id INT NOT NULL,
  number INT NOT NULL,
  name TEXT,
  size VARCHAR(8) NOT NULL,
  sizekbits BIGINT NOT NULL DEFAULT 0,
  episode_number INT DEFAULT NULL,
  last_modified DATETIME NOT NULL,
  PRIMARY KEY (id),
  CONSTRAINT updatepacklist_packs_bot_number UNIQUE (bot_id, number)
);
