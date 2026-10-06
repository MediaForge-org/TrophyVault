#!/bin/sh
# Creates the dedicated test database next to the development database. Idempotent.
# - Runs automatically when the postgres data volume is first initialised (sourced by the image entrypoint,
#   hence the subshell so that `set -e` cannot leak into it).
# - scripts/ensure-test-db.sh runs it again for volumes that already exist.
(
  set -eu
  psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<SQL
SELECT 'CREATE DATABASE "${POSTGRES_DB}_test" OWNER "${POSTGRES_USER}"'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = '${POSTGRES_DB}_test')\gexec
SQL
)
