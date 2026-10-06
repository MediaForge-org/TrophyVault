#!/bin/sh
# One-time local setup: creates .env with generated secrets. Never overwrites an existing .env.
set -eu
cd "$(dirname "$0")/.."

if [ -f .env ]; then
  echo ".env already exists - leaving it untouched."
  exit 0
fi

cp .env.example .env
key="base64:$(openssl rand -base64 32)"
db_pass="$(openssl rand -hex 16)"
redis_pass="$(openssl rand -hex 16)"

sed -i "s|^APP_KEY=.*|APP_KEY=${key}|; s|^DB_PASSWORD=.*|DB_PASSWORD=${db_pass}|; s|^REDIS_PASSWORD=.*|REDIS_PASSWORD=${redis_pass}|; s|^HOST_UID=.*|HOST_UID=$(id -u)|; s|^HOST_GID=.*|HOST_GID=$(id -g)|" .env
echo "Created .env (APP_PORT=$(grep '^APP_PORT=' .env | cut -d= -f2)). Review it, then run: make up"
