# 0008 — Storage conventions: time, enums, deletion, JSONB, secrets

Status: accepted · Date: 2026-10-07 · Resolves M0.3 open questions 2, 3 (partly), 4, 5

## Context
Without shared rules each table would invent its own timestamps, status strings, delete behaviour and JSON usage.
These rules are enforced by tests (`SchemaConventionsTest`, `EnumConsistencyTest`, `JsonbGuardTest`, `TimestampRoundTripTest`, `MigrationLifecycleTest`) wherever the database can be inspected.

## Decision

### 1. Time
- **All instants are `timestamptz`** (Laravel `timestampsTz()` / `timestampTz()`), stored and returned as UTC. PostgreSQL does not store the zone, it normalises to UTC;
  the connection pins `timezone = UTC` (`config/database.php`) and the app timezone is UTC. No `timestamp without time zone` anywhere (tested).
- Eloquent returns `CarbonImmutable` (`Date::use(CarbonImmutable::class)`).
- **Instants are bound with their UTC offset, system-wide.** Laravel formats every bound `DateTimeInterface` as `Y-m-d H:i:s` (no offset) for models, pivots *and* query-builder `where`/`insert`, so a Carbon value in `+02:00` would be stored — and compared — as a different instant. `App\Support\Database\OffsetAwarePostgresGrammar` (installed through the `pgsql` connection extension) uses `Y-m-d H:i:sP`. This is one mechanism for everything; there is no model-level override. Proven by `TimestampRoundTripTest` (UTC, +05:30, −08:00, +14:00, DST zones; inserts and comparisons).
- **Second precision, truncating.** Columns are `timestamp(0)`; the format carries no fraction, so sub-second parts are truncated (PostgreSQL would otherwise round `…59.6` up into the next second). DB → PHP → DB is stable.
- Calendar dates without a time (`game_releases.release_date`) use `date`. Display time zones are a presentation concern (per user, later).
- Naming: `created_at`, `updated_at` (every row-owning table), `*_at` for instants (`earned_at`, `retrieved_at`, `calculated_at`, `retired_at`, `last_synced_at`); `NULL` means "unknown/not yet", never a sentinel date.
- Provider timestamps are stored as reported (converted to the instant), our own observation times are separate columns (`first_seen_at`, `last_synced_at`, `retrieved_at`).

### 2. Enumerated values
| Kind of vocabulary | Mechanism | Examples |
| --- | --- | --- |
| Closed set that code branches on; changes need code anyway | `text` + named `CHECK (col IN (...))` **and** a PHP backed enum with the same cases | trophy grade, group classification, relation kinds, account status |
| Data-extensible reference vocabulary referenced by data/packs or shown in UIs | lookup table with `code` primary key, seeded by the migration that creates it | `platforms`, `regions`, `providers`, (planned) `objective_types` |
| Native PostgreSQL `ENUM` | **not used** — adding/removing a value is awkward and cannot be done in all transactional cases | — |
- `EnumConsistencyTest` fails if a PHP enum and its CHECK list diverge. Adding a value = one migration that replaces the CHECK + one enum case.
- `unknown` is a real value where the domain needs it (`trophy_groups.classification`, `regions.unknown`), never `NULL` standing for "unknown".
- Migrations use literal value lists (not the PHP enums) so old migrations never change meaning.

### 3. Deletion, retirement, versioning
| Data | Policy |
| --- | --- |
| Canonical catalog and trophy definitions (games, releases, trophy sets) | **No hard delete in normal operation; no `deleted_at`.** `retired_at timestamptz` marks "no longer active" (provider withdrew it, duplicate merged). FKs to them are `RESTRICT`. |
| Structural parts (groups, trophies of a set; platform/identifier link rows) | follow their owner (`CASCADE`); but anything with account progress `RESTRICT`s deletion |
| Knowledge (guides) | **versioned, not deleted**: `guide_versions` are immutable; objectives are retired in a version (see [ADR 0013](0013-objectives-trackers-guides.md)) |
| Personal data (account progress, user progress) | **hard delete**, cascades from the owner (user/PSN account). Re-syncable data may be rebuilt. |
| Sync/audit records (runs, errors, external records) | never cascade-deleted by domain deletes; retained per retention rules, then purged by a job |
| External identifier mappings | **retired**, not deleted (`retired_at`), so ids that a provider reuses can be re-assigned |
- Laravel `SoftDeletes` is not used anywhere.

### 4. JSONB policy
Allowed: rarely filtered provider metadata, type-specific optional objective/tracker settings, versioned `inputs` of calculations, bounded raw provider payload snapshots with retention.
**Not allowed in JSONB:** relationships/foreign keys, identifiers that are looked up (trophy ids, external ids), user progress, anything frequently filtered/sorted/joined on, secrets.
Rules, enforced by test:
1. A JSONB column is `NOT NULL DEFAULT '{}'` and has `CHECK (jsonb_is_safe_object(col))` (object, size, no credential-looking keys — see 2.).
2. The CHECK is one shared SQL function, `jsonb_is_safe_object(jsonb)` (migration `000150`): JSON **object**, **≤ 64 KiB** as text, and **no key at any depth** (nested objects and arrays included, **case-insensitive**) equal to a credential-looking name (`password`, `token`, `access_token`, `refresh_token`, `id_token`, `npsso`, `cookie(s)`, `secret`, `client_secret`, `api_key`, `authorization`, `bearer`, …). It is defence in depth, not a secret store, and it cannot see secret *values* (`{"url": "…?token=abc"}` passes) — never write secrets to JSONB. One definition means one place to extend the list (`CREATE OR REPLACE`).
3. A key that is queried by more than one feature gets promoted to a real column (with a migration), not an expression index on JSON.
4. Shapes of long-lived JSONB are documented in the data dictionary next to the column.

### 4b. Text columns
Human text (`title`, `name`) must be non-empty without leading/trailing whitespace of any kind; identifiers (`email`, `psn_account_id`, `online_id`, external-id `value`, group `set_key`) must contain no whitespace at all. Done with regex CHECKs (`~ '^\S+(\s+\S+)*$'`, `~ '^\S+$'`), because `btrim()` only strips spaces — a tab or newline would have created a second spelling of the same identifier (found by `ExternalIdentifierTest`).

### 5. Secrets
Credentials/tokens/cookies are never stored in domain columns or JSONB. They live in dedicated tables (planned `psn_account_credentials`, M2) using Laravel's `encrypted` cast, are hidden from serialisation, logs, exports and `import_errors`. See [`security-and-performance.md`](../data-model/security-and-performance.md).

## Consequences
- A second `timestamptz` reading rule: tests and code must never compare naive strings from the database.
- New vocabularies need an explicit choice (CHECK+enum or lookup table); the table above is the decision aid.
- Not having soft deletes means "undelete" is only possible for retire-able entities; that is intended.
- Database safety rules (separate test role, guarded destructive commands) are in [ADR 0014](0014-database-safety.md).
