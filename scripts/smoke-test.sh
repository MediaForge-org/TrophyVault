#!/bin/sh
# Infrastructure checks against the running development stack (docker compose up -d --wait).
# Verifies health, service reachability and the exposure rules. Exits non-zero on the first failure.
set -eu
cd "$(dirname "$0")/.."

fail() { echo "FAIL: $*" >&2; exit 1; }
ok()   { echo "ok:   $*"; }

port="$(grep -E '^APP_PORT=' .env | cut -d= -f2)"
[ -n "$port" ] || fail "APP_PORT missing in .env"
[ "$port" != "8080" ] || fail "APP_PORT must not be 8080"

# 1. Every service is running and healthy.
for svc in web app horizon frontend postgres redis; do
  state="$(docker compose ps --format '{{.Health}}' "$svc")"
  [ "$state" = "healthy" ] || fail "service $svc is '$state', expected healthy"
  ok "$svc healthy"
done

# 2. Only the web service publishes a host port, and only on loopback.
ports="$(docker compose ps --format '{{.Service}} {{.Ports}}')"
others="$(echo "$ports" | grep -v '^web ' | grep -- '->' || true)"
[ -z "$others" ] || fail "services other than web publish host ports: $others"
echo "$ports" | grep '^web ' | grep -q "127.0.0.1:${port}->80" || fail "web must publish 127.0.0.1:${port}->80, got: $(echo "$ports" | grep '^web ')"
ok "only web is published (127.0.0.1:${port})"

# 3. HTTP through the single entrypoint: backend health, laravel liveness, frontend page.
body="$(curl -fsS "http://127.0.0.1:${port}/api/health")" || fail "/api/health not reachable"
echo "$body" | grep -q '"status":"ok"' || fail "/api/health reports: $body"
ok "/api/health -> $body"
curl -fsS -o /dev/null "http://127.0.0.1:${port}/up" || fail "/up failed"
curl -fsS "http://127.0.0.1:${port}/" | grep -q '<div id="app">' || fail "frontend page not served"
ok "/up and frontend served"

# 4. Service-to-service connectivity over the internal network.
docker compose exec -T app php -r '
  $pdo = new PDO("pgsql:host=postgres;dbname=" . getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));
  exit($pdo->query("select 1")->fetchColumn() == 1 ? 0 : 1);' || fail "app cannot reach postgres"
ok "app -> postgres"
docker compose exec -T app php -r '
  $r = new Redis(); $r->connect("redis", 6379); $r->auth(getenv("REDIS_PASSWORD"));
  exit($r->ping() ? 0 : 1);' || fail "app cannot reach redis"
ok "app -> redis"
docker compose exec -T web wget -q -O /dev/null --header 'Host: localhost' http://frontend:5173/ || fail "web cannot reach frontend"
ok "web -> frontend"

# 5. Redis refuses unauthenticated access.
docker compose exec -T -e REDISCLI_AUTH= redis redis-cli ping 2>&1 | grep -q NOAUTH || fail "redis accepts unauthenticated connections"
ok "redis requires a password"

# 5b. The restricted test role cannot reach the development database.
if docker compose exec -T postgres sh -c 'PGPASSWORD="$TEST_DB_PASSWORD" psql -h 127.0.0.1 -U "$TEST_DB_USER" -d "$POSTGRES_DB" -At -c "select 1"' >/dev/null 2>&1; then
  fail "the test role can connect to the development database"
fi
ok "test role is locked out of the development database"

# 6. Dedicated test database exists.
docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d postgres -Atc "select 1 from pg_database where datname = '"'"'${POSTGRES_DB}_test'"'"'"' | grep -q 1 \
  || fail "test database missing (run scripts/ensure-test-db.sh)"
ok "test database present"

echo "Smoke test passed."
