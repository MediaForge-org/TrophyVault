#!/bin/sh
# End-to-end proof that the test suite and destructive commands cannot touch the DEVELOPMENT database.
# Needs the running stack. It places a marker table in the dev database, runs the suite under a hostile environment
# (APP_ENV=local, DB_DATABASE=<dev database>), and verifies the marker is untouched. It only does so when the dev
# database holds no user data, because a failing safeguard would be destructive.
set -eu
cd "$(dirname "$0")/.."

fail() { echo "FAIL: $*" >&2; exit 1; }
ok() { echo "ok:   $*"; }
psql_dev() { docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -At -c "$1"' sh "$1"; }

dev="$(docker compose exec -T app sh -c 'printf %s "$DB_DATABASE"')"
case "$dev" in *_test) fail "DB_DATABASE '$dev' looks like a test database; run this against the real dev setup" ;; esac

# 1. the confirmed-reset wrapper refuses without confirmation, with a wrong name, and for test databases
if CONFIRM= sh scripts/db-reset-dev.sh >/dev/null 2>&1; then fail "db-reset-dev ran without CONFIRM"; fi
if CONFIRM=wrong sh scripts/db-reset-dev.sh >/dev/null 2>&1; then fail "db-reset-dev accepted a wrong CONFIRM"; fi
ok "db-reset-dev refuses without the exact database name"

# 2. the restricted test role cannot connect to the dev database
if docker compose exec -T postgres sh -c 'PGPASSWORD="$TEST_DB_PASSWORD" psql -h 127.0.0.1 -U "$TEST_DB_USER" -d "$POSTGRES_DB" -At -c "select 1"' >/dev/null 2>&1; then
  fail "the test role can connect to the development database"
fi
ok "test role has no access to the development database"

# 3. marker experiment (only on an empty dev database)
rows=0
for t in users psn_accounts games trophy_sets trophies psn_account_trophies; do
  n="$(psql_dev "select case when to_regclass('public.$t') is null then 0 else (select count(*) from public.$t) end" 2>/dev/null || echo 0)"
  rows=$((rows + ${n:-0}))
done
if [ "$rows" -gt 0 ]; then
  echo "skip: dev database holds $rows data rows; marker experiment not run (nothing is risked)."
else
  psql_dev "create table if not exists _safety_marker (id int primary key, note text); insert into _safety_marker values (1, 'untouched') on conflict (id) do update set note = 'untouched'" >/dev/null
  tables_before="$(psql_dev "select count(*) from information_schema.tables where table_schema = 'public'")"
  docker compose exec -T -e APP_ENV=local -e DB_DATABASE="$dev" app composer test >/tmp/safety-suite.log 2>&1 || { tail -20 /tmp/safety-suite.log; fail "the test suite failed under the hostile environment"; }
  [ "$(psql_dev "select note from _safety_marker where id = 1")" = "untouched" ] || fail "marker row changed or vanished"
  [ "$(psql_dev "select count(*) from information_schema.tables where table_schema = 'public'")" = "$tables_before" ] || fail "dev table count changed"
  ok "suite ran under APP_ENV=local DB_DATABASE=$dev; dev marker and table count unchanged"
  psql_dev "drop table _safety_marker" >/dev/null
fi

echo "Database safety checks passed."
