#!/bin/sh
# Provisions the isolated test database and its restricted role. Idempotent.
#  - role  <TEST_DB_USER>   : LOGIN only (no superuser/createdb/createrole), owns the test database, may NOT connect to the dev database
#  - db    <POSTGRES_DB>_test: disposable; recreated if it is owned by anyone else
# Runs automatically when the postgres volume is first initialised (sourced by the image entrypoint, hence the subshell)
# and again via scripts/ensure-test-db.sh for existing volumes.
(
  set -eu
  tdb="${POSTGRES_DB}_test"
  trole="${TEST_DB_USER:?TEST_DB_USER must be set (DB_TEST_USERNAME in .env)}"
  tpw="${TEST_DB_PASSWORD:?TEST_DB_PASSWORD must be set (DB_TEST_PASSWORD in .env)}"
  case "$tdb" in *_test) ;; *) echo "refusing: '$tdb' is not a *_test database" >&2; exit 1 ;; esac
  [ "$trole" != "$POSTGRES_USER" ] || { echo "refusing: the test role must differ from the dev role" >&2; exit 1; }

  psql -v ON_ERROR_STOP=1 -v ddb="$POSTGRES_DB" -v tdb="$tdb" -v trole="$trole" -v tpw="$tpw" \
    --username "$POSTGRES_USER" --dbname postgres <<'SQL'
SELECT format('CREATE ROLE %I LOGIN', :'trole')
WHERE NOT EXISTS (SELECT FROM pg_roles WHERE rolname = :'trole') \gexec
ALTER ROLE :"trole" WITH LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD :'tpw';
-- A *_test database owned by someone else is disposable: recreate it so the test role owns every object in it.
SELECT format('DROP DATABASE %I WITH (FORCE)', :'tdb')
FROM pg_database d JOIN pg_roles r ON r.oid = d.datdba
WHERE d.datname = :'tdb' AND r.rolname <> :'trole' \gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'tdb', :'trole')
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = :'tdb') \gexec
-- The test role must never reach the development database.
REVOKE ALL ON DATABASE :"ddb" FROM PUBLIC;
REVOKE ALL ON DATABASE :"ddb" FROM :"trole";
SQL
)
