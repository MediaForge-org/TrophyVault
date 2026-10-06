# Database safety: keeping tests and commands away from real data

Decided in [ADR 0014](../decisions/0014-database-safety.md). This page is the operating manual: what protects what, how to run destructive operations deliberately, and how each layer is proven.

## What went wrong (incident, M0.3)

`php artisan migrate:fresh --env=testing` was run inside the app container to "reset the test database". It reset the **development** database instead (it held only seeded reference data, no user data — but the mechanism was real).

**Root cause (two independent defects):**
1. `--env=testing` only selects the application *environment label* (and would load a `.env.testing` file, which does not exist). The containers already carry the development credentials in real environment variables (`DB_DATABASE=trophyvault`), and real variables win over files. So the label said "testing" while the connection pointed at the development database.
2. Every protection that existed only worked for PHPUnit: `phpunit.xml` forces `DB_DATABASE=trophyvault_test`, and `Tests\TestCase` refuses other names. Plain artisan commands never went through either. Laravel's own `ConfirmableTrait` only prompts in `production`.

A second finding during the hardening run: the obvious fix — listening to Laravel's `CommandStarting` event — **does not work exactly in the dangerous case**: the console kernel does not dispatch that event when the environment label is `testing` (`Kernel::handle()` skips `rerouteSymfonyCommandEvents()` for `runningUnitTests()`). A guard that trusts the label can be bypassed by the label itself. The guard therefore lives in the command classes and never looks at the label.

A third finding came from the second hardening round, again reproduced against nonexistent decoys: a guard that reads `database.connections.pgsql.database` can be bypassed with `DB_DATABASE=x_test DB_URL=pgsql://…/trophyvault`, because `DB_URL` overrides the database name when the connection is built. The guard therefore resolves the *effective* target with the same `ConfigurationUrlParser` Laravel uses, on the current config.

## Layers (defense in depth)

| # | Layer | Protects against | Where |
| --- | --- | --- | --- |
| 1 | **Restricted PostgreSQL role for tests** (`DB_TEST_USERNAME`, owns only `<db>_test`, `REVOKE CONNECT` on the dev DB) | any test/phpunit run reaching the dev database, whatever the configuration says | `docker/postgres/init-test-db.sh`, `tests/bootstrap.php` |
| 2 | **`phpunit.xml` forces** `APP_ENV=testing`, `DB_DATABASE=trophyvault_test`, empty `DB_URL`, test-only Redis DBs (set as `<env>` *and* `<server>`) | environment variables of the container leaking into the suite | `backend/phpunit.xml` |
| 3 | **`Tests\TestCase` guard**: config name must end in `_test`, label must be `testing`, and the *live* connection must report `current_database()` ending in `_test` and `current_user` equal to the test role | misconfiguration and DB_URL/host surprises — it asks the server, not the config | `tests/TestCase.php`, `tests/Support/TestDatabaseGuard.php` |
| 4 | **Guarded destructive commands**: `migrate:fresh/refresh/reset/rollback`, `db:wipe` refuse any database that is not `*_test`, unless explicitly named in `TROPHYVAULT_ALLOW_DESTRUCTIVE_DB`; never in production; `--database=` is honoured and `DB_URL` overrides are resolved (an unknown connection counts as "not a test database") | an operator or script running the command against the wrong target — the incident | `app/Console/Commands/Guarded/*`, `App\Support\Database\DestructiveCommandPolicy` |
| 5 | **Laravel's `prohibitDestructiveCommands`** in production | belt and braces for production | `AppServiceProvider::boot()` |
| 6 | **Own wrappers validate the target** before destruction; no script calls `migrate:fresh` blindly | Makefile/CI scripts | `make db-fresh-test`, `make db-reset-dev`, `scripts/db-reset-dev.sh` |
| 7 | **CI cannot see a dev database** and a static checker keeps it that way | CI configuration drift | `scripts/check-ci.py` (+ `--selftest`) |

The policy (`DestructiveCommandPolicy`), first match wins: production → refuse always; database name ends in `_test` → allow; `TROPHYVAULT_ALLOW_DESTRUCTIVE_DB` equals the **exact** database name → allow; otherwise refuse. The opt-in is a *name*, not a flag: `yes`, `1`, `true`, `*` or the name of another database never unlock anything.

## Daily use

```sh
make test-backend                     # PHPUnit: restricted role, trophyvault_test only
make db-fresh-test                    # rebuild the disposable test database (migrate:fresh, restricted role)
make db-reset-dev CONFIRM=trophyvault # DESTRUCTIVE, deliberate: reset the development database
make db-reset-dev CONFIRM=trophyvault WIPE_DATA=yes   # only if it holds data and you mean it
make safety-test                      # end-to-end proof incl. a marker in the dev database
make ci-check                         # CI workflow + compose safety rules and their self-test
```

`db-reset-dev` reads the database name from the *running container* (not from your shell), requires you to type it back, refuses `*_test` names and production, counts user/canonical rows and refuses to run on a database that holds data unless `WIPE_DATA=yes`. Only then it sets the one-off opt-in `TROPHYVAULT_ALLOW_DESTRUCTIVE_DB=<name>` for that single artisan call. Never put the opt-in into `.env`.

**Never** use `--env=testing` to "select" the test database. To run an artisan command against the test DB, use `make db-fresh-test` (or set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` explicitly).

## Production

- Destructive commands are refused by layer 4 and 5 regardless of any flag.
- Production needs its own credentials and database; the restricted-role pattern (a role that owns only what it must) is the template. Production deployment is out of scope for M0.
- `pg_trgm` is a *trusted* extension: a non-superuser database owner can create it (needed by the first migration).

## How each layer is proven

| Case | Proof |
| --- | --- |
| A — normal runs use only `trophyvault_test` | `ConnectionIdentityTest`, `TestCase` live-connection check, `PostgresTest` |
| B — hostile environment (`APP_ENV=local`, `DB_DATABASE=<dev>`) cannot redirect a run | `DatabaseSafetyProcessTest::test_case_b_…` (read-only probe in a subprocess) and `make safety-test` (full suite + marker) |
| C — a deliberately wrong non-test DB is rejected | `DatabaseSafetyProcessTest::test_case_c_…` with an *unforced* PHPUnit config (`tests/Safety/phpunit.unforced.xml`) against a decoy name |
| D — a marker in the dev database survives the suite | `make safety-test` (skipped automatically when the dev DB holds data) |
| E — CI config cannot select a dev database or expose services | `scripts/check-ci.py` + 8 mutation self-tests |
| F — own scripts validate the target | `make safety-test` (db-reset-dev refusals); `DatabaseSafetyProcessTest` for the commands; `DestructiveCommandPolicyTest` for the policy |
| role isolation | `DatabaseSafetyProcessTest::test_the_restricted_test_role_cannot_connect_to_the_development_database`, `scripts/smoke-test.sh` |

**All destructive attempts in the automated tests target decoy databases that do not exist** (`decoy_not_a_test_db`, `decoy_guard_test`), so a broken guard fails a test but cannot damage real data. The only tests that ever see the real dev database name run read-only probes.

## Known limits

- The development role (`POSTGRES_USER`) is a superuser and *can* reach the test database; protection against *it* is layers 4–6, not a permission.
- A person with `psql` and the dev password can still drop the dev database. That is not an accident scenario this design tries to prevent.
- `DB::statement('DROP …')` or raw SQL in application code is outside the command guard (and would be a code-review matter).
