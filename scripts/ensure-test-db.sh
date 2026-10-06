#!/bin/sh
# Idempotently creates the "<DB>_test" database inside the running postgres service.
set -eu
cd "$(dirname "$0")/.."
docker compose exec -T postgres sh /docker-entrypoint-initdb.d/init-test-db.sh
