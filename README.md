# TrophyVault

TrophyVault is intended to become a self-hosted, personal PlayStation trophy and 100%-completion platform.

> **Status: very early development — M0.1 (Project Foundation) is done; M0.2 (Domains & Product Boundaries) is documentation only.**
> Only the technical foundation exists: project skeleton, Docker development environment, CI, tooling and a health check, plus
> the architecture documents in [`docs/architecture/`](docs/architecture/README.md). The data model (M0.3) is not designed yet.
> There is **no** PSN integration, trophy data, guides, completion tracking, dashboard or user accounts yet.

## Stack

| Area | Technology |
| --- | --- |
| Backend | PHP 8.5, Laravel 13 |
| Frontend | Vue 3, TypeScript, Vite, Tailwind CSS 4 |
| Database | PostgreSQL 18 |
| Cache / queue / sessions | Redis 8, Laravel Queue, Laravel Horizon |
| Infrastructure | Docker, Docker Compose |
| Quality | PHPUnit, Larastan (PHPStan level 9), Laravel Pint, Vitest, ESLint (type-aware, strict), vue-tsc |
| CI | GitHub Actions |

## Prerequisites

- Docker with Compose v2 (`docker compose`)
- `make`, `openssl`, `curl`, `sh` (only convenience; every make target is a plain command, see the `Makefile`)

PHP, Composer and Node do **not** need to be installed locally — everything runs in containers.

## Setup

```sh
git clone git@github.com:MediaForge-org/TrophyVault.git && cd TrophyVault
make setup      # creates .env with generated secrets (never overwrites an existing .env)
make build      # builds the PHP and Node images
make install    # composer install (inside a container)
make up         # starts the stack, waits for healthchecks, ensures the test database exists
make migrate    # migrates the development database
```

Then open <http://127.0.0.1:8190>.

### Environment

`.env.example` documents every variable. `make setup` copies it to `.env` and generates `APP_KEY`, `DB_PASSWORD` and `REDIS_PASSWORD`.
`.env` is git-ignored and must never be committed.

### Host port

The only port published on the host is the web entrypoint: **`127.0.0.1:8190`** (`APP_PORT` in `.env`).

- Chosen on 2026-10-06 after checking the machine's listening sockets (`ss -tln`) and running containers (`docker ps`); 8190 was free.
- Host port **8080 is taken by SABnzbd** and is not used. Change `APP_PORT` (and `APP_URL`) in `.env` if 8190 is taken on your machine.
- It is bound to loopback only. PostgreSQL, Redis, PHP-FPM and the Vite dev server are **not** published; they are reachable only on the internal Docker networks.

### Services

| Service | Image / role | Exposed |
| --- | --- | --- |
| `web` | nginx — single entrypoint: `/api`, `/up`, `/horizon` → Laravel, everything else → Vite (incl. HMR) | `127.0.0.1:${APP_PORT}` |
| `app` | PHP 8.5-FPM running Laravel; also used for artisan/composer/tests | no |
| `horizon` | Laravel Horizon queue supervisor | no |
| `frontend` | Vite dev server (Node 22) | no |
| `postgres` | PostgreSQL 18 (dev DB + dedicated test DB) | no |
| `redis` | Redis 8 (password protected, AOF) | no |

All services have healthchecks. The Horizon dashboard (`/horizon`) is reachable in the `local` environment only.

## Development commands

```sh
make up / make down / make ps / make logs
make migrate           # development database
docker compose exec app php artisan <command>
docker compose exec frontend npm <command>
```

If the Vite dev server does not pick up file changes (some file systems, e.g. NTFS/network shares, send no change events),
set `VITE_USE_POLLING=true` in `.env`.

## Tests

```sh
make test              # backend + frontend
make test-backend      # PHPUnit against PostgreSQL (trophyvault_test) and Redis
make test-frontend     # Vitest (headless, jsdom)
make smoke             # infrastructure smoke test against the running stack
```

### Development vs. test database

| | Development | Test |
| --- | --- | --- |
| PostgreSQL database | `trophyvault` (`DB_DATABASE`) | `trophyvault_test` (`<DB_DATABASE>_test`) |
| Redis DBs | `0` (default/queue/session), `1` (cache) | `14`, `15`, key prefix `trophyvault-test-` |
| Created by | `postgres` image on first start | `docker/postgres/init-test-db.sh` (also `scripts/ensure-test-db.sh`) |

Safeguards against tests touching development data:

1. `backend/phpunit.xml` **forces** `APP_ENV=testing`, `DB_DATABASE=trophyvault_test`, array cache/session, sync queue and separate Redis DBs/prefix — even if the container environment says otherwise.
2. `Tests\TestCase::createApplication()` aborts with an exception, before any test trait touches the database, unless the environment is `testing` **and** the database name ends in `_test`. The rule itself is unit-tested (`TestDatabaseGuardTest`).
3. Verified manually: running the suite with `DB_DATABASE=trophyvault APP_ENV=local` in the environment still uses `trophyvault_test` and leaves the development database untouched.

Tests use PostgreSQL — never SQLite.

## Quality commands

```sh
make lint              # Pint (check) + ESLint
make analyse           # PHPStan / Larastan, level 9
make typecheck         # vue-tsc
make build-frontend    # production build
make format            # auto-format backend (Pint)
make check             # everything CI runs
```

## CI

`.github/workflows/ci.yml` runs on pushes to `main` and on pull requests:

- **backend** — Pint, PHPStan, PHPUnit against PostgreSQL 18 and Redis service containers
- **frontend** — ESLint, vue-tsc, Vitest, production build
- **stack** — `docker compose config`, image build, stack start with healthchecks, `scripts/smoke-test.sh`

No deployment pipeline exists yet.

## Repository structure

```
backend/     Laravel 13 application (API, queue, Horizon)
frontend/    Vue 3 + TypeScript + Vite + Tailwind SPA (placeholder page)
docker/      Dockerfiles and configuration (php, node, nginx, postgres)
scripts/     setup, test-database creation, infrastructure smoke test
docs/        architecture documents (docs/architecture) and decision records (docs/decisions)
.github/     GitHub Actions workflows
```

See [`docs/`](docs/README.md) for the domain architecture and decisions, and [`CLAUDE.md`](CLAUDE.md) for the project rules (including the git rules for AI assistants).

## Security notes

This is a **development** foundation, not a production deployment: debug mode is on, and no hardening beyond sane defaults has been done.
No secrets are committed; `.gitignore` already covers `.env*`, keys, cookies and token files for the future PSN integration.
