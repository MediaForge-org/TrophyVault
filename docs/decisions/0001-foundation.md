# 0001 — M0.1 foundation decisions

Status: accepted · Date: 2026-10-06

## Stack versions (verified when the foundation was built)

PHP 8.5.11, Laravel 13.17, Horizon 5.x, Larastan 3.x, PHPUnit 12, Pint 1.x, PostgreSQL 18, Redis 8, Node 22,
Vue 3.5, Vite 8, Tailwind CSS 4.3, TypeScript 6, Vitest 5, ESLint 10 (typescript-eslint 8). No incompatibilities with the
target stack were found. `composer.lock` / `package-lock.json` pin exact versions.

## Decisions

1. **Monorepo with `backend/` and `frontend/`.** Two clearly separated applications, one repository, one compose file. Simple and no deep hierarchy.
2. **Single host entrypoint through nginx.** The browser talks to one origin; nginx routes `/api`, `/up`, `/horizon` to PHP-FPM and everything else to the Vite dev server (with HMR websocket).
   No CORS configuration needed, only one host port to pick/check, and the same shape a production deployment would have (static build behind the same nginx).
3. **Only `web` is published, on `127.0.0.1:${APP_PORT}`.** PostgreSQL, Redis, PHP-FPM and Vite are internal. The port is configurable because the host runs many other services (8080 is SABnzbd).
4. **One PostgreSQL server, two databases** (`trophyvault`, `trophyvault_test`) instead of a second container: cheaper, and the separation is enforced in code (forced phpunit env + `_test` guard in `Tests\TestCase`). CI uses a throw-away PostgreSQL service whose only database is the test DB.
5. **Redis for queue, cache and sessions**, password-protected, with separate DB indexes and key prefix for tests. Horizon runs as its own container.
6. **PHPUnit instead of Pest.** Laravel's default, no extra abstraction; sufficient for the planned test layers (Unit / Feature / Integration suites exist; `Integration` is for tests needing real PostgreSQL/Redis).
7. **Larastan at level 9.** Strict from the start is cheap while the codebase is tiny. Framework-generated config files were adjusted for typing instead of excluding them from analysis.
8. **Health check:** `GET /api/health` reports database and Redis status (200/503) without leaking error details (they are logged). `GET /up` is Laravel's liveness route and is used for the web/app healthchecks.
9. **No user accounts yet.** The default users/cache/jobs migrations were dropped (Redis replaces the cache/jobs tables); only `failed_jobs` remains for Laravel's failed-job storage. The Horizon dashboard gate denies everyone outside `local`.
10. **Explicit setup steps.** `make install`/`make migrate` are separate commands; containers do not run installs or migrations implicitly (except the frontend container's `npm ci`, whose `node_modules` live in a named volume).
11. **`composer test` runs PHPUnit directly** rather than `artisan test`, which, inside the container without a `.env` file, reports phpdotenv's suppressed missing-file warning as a test warning.

## Known limitations / later work

- Development configuration only; no production images, TLS, or deployment pipeline.
- E2E tests, API contract tests, provider/import/idempotency tests come with the features they cover.
- Horizon dashboard authorisation needs real accounts (later milestone).
