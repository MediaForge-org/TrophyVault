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
- Infrastructure: `make smoke` (stack must be running). `make check` runs everything CI runs.
- A work package is done only when tests, static analysis, linting/formatting, typecheck, build, Docker healthchecks and docs are green/updated.

## Environment notes

- Host port 8080 belongs to SABnzbd. TrophyVault's published port is `APP_PORT` (default 8190, loopback only). Check ports
  read-only (`ss -tln`, `docker ps`) before choosing one; never stop or modify other containers/processes.
- PostgreSQL and Redis must not be published on host ports.
- The working copy may live on a file system without exec bits/symlinks (NTFS via fuseblk): run tools through
  `php vendor/bin/...`/`docker compose`, and keep `node_modules` in the Docker volume (already configured).
