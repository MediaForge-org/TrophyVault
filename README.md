# TrophyVault

TrophyVault is intended to become a self-hosted, personal PlayStation trophy and 100%-completion platform.

> **Status: very early development.** M0.1 (Project Foundation) and M0.2 (Domains & Product Boundaries) are done; **M0.3 (PostgreSQL data model)** defines the v1 data architecture
> ([`docs/data-model/`](docs/data-model/README.md)) and migrates its structural core: reference data, catalog, trophy structure, external identifiers, users/PSN accounts and account trophy progress (20 tables).
> There is **no** PSN integration, sync, guide/objective/tracker tables, completion engine, dashboard or user-facing UI yet — only the schema, its tests and a health check.
> The remaining v1 tables are designed but intentionally not migrated until their milestone ([migration roadmap](docs/data-model/migration-roadmap.md)).

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

> **Existing checkout?** `.env` now needs `DB_TEST_USERNAME` and `DB_TEST_PASSWORD` (restricted role for the test database, see `.env.example`; `docker compose` refuses to start without them). Add them, then run `make up` — `scripts/ensure-test-db.sh` provisions the role and recreates the disposable `<db>_test` database. The development database is not touched.

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
make docs-check        # Markdown links/anchors + Mermaid syntax of the docs
```

### Development vs. test database

| | Development | Test |
| --- | --- | --- |
| PostgreSQL database | `trophyvault` (`DB_DATABASE`) | `trophyvault_test` (`<DB_DATABASE>_test`) |
| Redis DBs | `0` (default/queue/session), `1` (cache) | `14`, `15`, key prefix `trophyvault-test-` |
| Created by | `postgres` image on first start | `docker/postgres/init-test-db.sh` (also `scripts/ensure-test-db.sh`) |

Safeguards against tests (and destructive commands) touching development data — layered, see [`docs/data-model/database-safety.md`](docs/data-model/database-safety.md):

1. **Restricted database role:** the suite runs as `DB_TEST_USERNAME`, which owns only `<DB_DATABASE>_test` and has no `CONNECT` privilege on the development database (`docker/postgres/init-test-db.sh`, `backend/tests/bootstrap.php`).
2. `backend/phpunit.xml` **forces** `APP_ENV=testing`, `DB_DATABASE=trophyvault_test`, array cache/session, sync queue and separate Redis DBs/prefix — even if the container environment says otherwise.
3. `Tests\TestCase` aborts before any test trait touches the database unless the label is `testing`, the configured name ends in `_test` **and** the live connection reports a `*_test` database and the test role.
4. **Destructive artisan commands** (`migrate:fresh|refresh|reset|rollback`, `db:wipe`) refuse every database that is not `*_test` — whatever `--env` says — unless explicitly named via `TROPHYVAULT_ALLOW_DESTRUCTIVE_DB`. Use the wrappers:
   - `make db-fresh-test` — rebuild the disposable test database
   - `make db-reset-dev CONFIRM=<database name>` — deliberate reset of the development database (refuses if it holds data unless `WIPE_DATA=yes`)
5. `make safety-test` proves it end to end (hostile environment, marker in the dev database); `make ci-check` keeps CI/compose from regressing.

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
- **docs** — Markdown links/anchors and Mermaid syntax of the ER diagrams
- **stack** — `docker compose config`, image build, stack start with healthchecks, `scripts/smoke-test.sh`

No deployment pipeline exists yet.

## Repository structure

```
backend/     Laravel 13 application (API, queue, Horizon)
frontend/    Vue 3 + TypeScript + Vite + Tailwind SPA (placeholder page)
docker/      Dockerfiles and configuration (php, node, nginx, postgres)
scripts/     setup, test-database creation, infrastructure smoke test
docs/        architecture (docs/architecture), data model (docs/data-model), decision records (docs/decisions)
.github/     GitHub Actions workflows
```

See [`docs/`](docs/README.md) for the domain architecture and decisions, and [`CLAUDE.md`](CLAUDE.md) for the project rules (including the git rules for AI assistants).

## Security notes

This is a **development** foundation, not a production deployment: debug mode is on, and no hardening beyond sane defaults has been done.
No secrets are committed; `.gitignore` already covers `.env*`, keys, cookies and token files for the future PSN integration.
