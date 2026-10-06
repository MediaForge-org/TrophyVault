#!/bin/sh
# Deliberate, confirmed reset of the DEVELOPMENT database (migrate:fresh). Never used implicitly.
#   make db-reset-dev CONFIRM=<database name>            (refuses if the database holds user data)
#   make db-reset-dev CONFIRM=<database name> WIPE_DATA=yes
# Safety: the target name is read from the running container (not from your shell), must be typed back,
# must not be a *_test database, and the app's own guard additionally requires TROPHYVAULT_ALLOW_DESTRUCTIVE_DB=<name>.
set -eu
cd "$(dirname "$0")/.."

name="$(docker compose exec -T app sh -c 'printf %s "$DB_DATABASE"')"
env_label="$(docker compose exec -T app sh -c 'printf %s "$APP_ENV"')"

[ -n "$name" ] || { echo "Cannot determine the development database name." >&2; exit 1; }
case "$name" in
  *_test) echo "'$name' is a test database - use: make db-fresh-test" >&2; exit 1 ;;
esac
[ "$env_label" != "production" ] || { echo "Refusing: APP_ENV=production." >&2; exit 1; }
[ "${CONFIRM:-}" = "$name" ] || { echo "Refusing: to reset the DEVELOPMENT database '$name' run: make db-reset-dev CONFIRM=$name" >&2; exit 1; }

# Count rows in the tables that hold user/canonical data (reference data is re-seeded by migrations).
rows=0
for t in users psn_accounts games editions game_releases trophy_sets trophy_groups trophies psn_account_trophy_sets psn_account_trophies; do
  n="$(docker compose exec -T postgres sh -c "psql -U \"\$POSTGRES_USER\" -d \"\$POSTGRES_DB\" -At -c \"select case when to_regclass('public.$t') is null then 0 else (select count(*) from public.$t) end\"" 2>/dev/null || echo 0)"
  rows=$((rows + ${n:-0}))
done
echo "Database '$name' currently holds $rows user/canonical rows."
if [ "$rows" -gt 0 ] && [ "${WIPE_DATA:-}" != "yes" ]; then
  echo "Refusing: it contains data. Re-run with WIPE_DATA=yes if you really want to destroy it." >&2
  exit 1
fi

docker compose exec -T -e TROPHYVAULT_ALLOW_DESTRUCTIVE_DB="$name" app php artisan migrate:fresh --force
