# niblmysql migrations

Ordered, idempotent SQL patches applied against the `ooinuza` database by
`../migrate.sh`. Files run in filename order, each exactly once; applied
filenames are recorded in `ooinuza.schema_migrations`.

Naming convention:

    0001_short_description.sql
    0002_another_change.sql

Rules:

- One change per file. Prefer idempotent statements (CREATE TABLE IF NOT EXISTS,
  ALTER TABLE ... ADD COLUMN, INSERT ... ON DUPLICATE KEY UPDATE, UPDATE ...).
- Do not include `USE ooinuza;` — migrate.sh runs each file against `ooinuza`.
- Never edit a file that has already been applied; add a new numbered file.