# TrophyVault — instructions for Claude

## Project

TrophyVault is a self-hosted, personal PlayStation trophy and 100%-completion platform. It is in very early development
(milestone M0, foundation only). Do not describe features as implemented that are not.

## Stack

PHP 8.5 · Laravel 13 · PostgreSQL 18 · Redis · Laravel Queue + Horizon · Vue 3 · TypeScript · Vite · Tailwind CSS 4 ·
Docker Compose · GitHub Actions. Laravel is the single central backend; no extra microservices.
Quality tooling: PHPUnit, Larastan (level 9), Pint, Vitest, ESLint, vue-tsc.

Layout: `backend/` (Laravel), `frontend/` (Vue), `docker/`, `scripts/`, `docs/`. See `README.md`.

## Git rules — ABSOLUTE

**Claude must NEVER commit or push.** The user controls all commits and pushes themselves.

- Forbidden: `git commit`, `git push`, `git tag`, creating releases, publishing branches, force-pushing, amending/rebasing history,
  or any other way of committing or publishing changes (including via `gh`).
- Allowed: reading git state (`git status`, `git diff`, `git diff --check`, `git log`, …), editing files, running tests,
  preparing changes and **suggesting** a commit message.
- Finish every work package with: summary of changes, `git status`, checks run and their results, remaining problems,
  and one proposed commit message — then stop.

## Domain rules (M0.2 — see `docs/architecture/`) and data model (M0.3 — see `docs/data-model/`)

- External providers (PSN, …) never define the domain: `Provider → Adapter → Normalization → Domain`. Provider ids are external identifiers.
- Canonical, Knowledge and Personal data are strictly separate; references point from personal to shared data only.
- `Game`, `GameRelease` and `TrophySet` are distinct (no 1:1 assumptions); a `TrophyGroup` is not automatically DLC.
- Objectives are recursive; Platinum is not completion (no single status enum mixing completion dimensions).
- Data model conventions (`docs/data-model/conventions.md`, ADRs 0007–0013): UUIDv7 primary keys; all instants `timestamptz`/UTC (`CarbonImmutable`); closed vocabularies = CHECK + PHP enum (kept in sync by test), extensible ones = lookup tables, no native PG enums; no Laravel soft deletes (`retired_at` for canonical data); JSONB only for unqueried provider/type-specific data and never for secrets, relations or progress; invariants belong in the database (FKs, composite same-owner FKs, partial uniques, CHECKs) *and* get a test.
- Earned trophy state belongs to the **PSN account** (`psn_account_trophies`), completion is **derived**, never stored. Provider ids live in `external_identifiers`, never as primary keys.
- Models live in `app/Models/<Domain>/`; create `app/Domain/<Domain>/` only with its first real class. Adding a migration means updating `docs/data-model/` (logical model, ER diagram, roadmap, regenerated data dictionary) — `DocumentationConsistencyTest` enforces it. Never edit a migration that was already committed; add a new one.
- Tables not yet migrated (guides, objectives, trackers, sync/provenance, planning, …) follow the logical model and roadmap; do not invent variants without an ADR.

## Development principles

- Architecture and quality before blind fast coding — but develop iteratively.
- No unnecessary enterprise complexity. Do not adopt something just because it sounds modern.
- Use PostgreSQL meaningfully (constraints, indexes, transactions); never substitute SQLite in tests.
- Clear domain boundaries.
- External systems (PSN, guide sources, …) later behind providers/adapters.
- Imports later must be idempotent; data provenance must be traceable.
- Schema changes through migrations only, never manual DB manipulation.
- Keep documentation up to date in the same change (README, `docs/`, this file).
- Never weaken or delete tests, disable static-analysis rules without good reason, or hide failures (`|| true`).
- No secrets, tokens, cookies, PSN credentials or personal data in the repository.

## Testing

Tests are part of an implementation, not an optional follow-up. Planned layers: unit, feature, integration, API, database,
provider, import, idempotency, queue, error/retry, security, frontend component/integration, later E2E.
No filler tests; test real behaviour.

- Backend tests run against PostgreSQL database `trophyvault_test` and Redis DBs 14/15. `Tests\TestCase` refuses to run
  against any database not ending in `_test` — never bypass this guard.
- Backend (in containers): `make test-backend`, `make lint-backend`, `make analyse`.
- Frontend: `make test-frontend`, `make lint-frontend`, `make typecheck`, `make build-frontend`.
- Infrastructure: `make smoke` (stack must be running). Docs: `make docs-check`. `make check` runs everything CI runs.
- **Database safety (ADR 0014, `docs/data-model/database-safety.md`):** never run `migrate:fresh`/`refresh`/`reset`/`rollback`/`db:wipe` against the dev database, and never use `--env=testing` to pick a database (it only changes a label). Use `make db-fresh-test` (test DB) or `make db-reset-dev CONFIRM=<name>` (deliberate, human decision). Demonstrate or test destructive behaviour only against **nonexistent decoy databases**. The test suite runs as a restricted role on `<db>_test`; do not weaken `phpunit.xml`, `tests/bootstrap.php`, `TestCase`, the guarded commands or `scripts/check-ci.py`.
- A work package is done only when tests, static analysis, linting/formatting, typecheck, build, Docker healthchecks and docs are green/updated.

## Environment notes

- Host port 8080 belongs to SABnzbd. TrophyVault's published port is `APP_PORT` (default 8190, loopback only). Check ports
  read-only (`ss -tln`, `docker ps`) before choosing one; never stop or modify other containers/processes.
- PostgreSQL and Redis must not be published on host ports.
- The working copy may live on a file system without exec bits/symlinks (NTFS via fuseblk): run tools through
  `php vendor/bin/...`/`docker compose`, and keep `node_modules` in the Docker volume (already configured).
