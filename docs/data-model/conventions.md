# Data model conventions

Rules every table follows. Where the database can be inspected, they are enforced by tests (`backend/tests/Integration/Schema/`).

## Identifiers ([ADR 0007](../decisions/0007-primary-keys-uuidv7.md))
- Surrogate primary key: `id uuid` = UUIDv7, `DEFAULT uuidv7()`; Eloquent generates it client-side (`HasUuidPrimaryKey`).
- Reference tables: `code text` primary key (`providers`, `platforms`, `regions`), lower-case snake format enforced by CHECK.
- Link/state tables without own attributes: natural composite primary key.
- Foreign-key columns are named `<entity>_id` (`game_id`, `trophy_set_id`) or `from_<entity>_id`/`to_<entity>_id` in relation tables.
- Human handles are separate columns (`slug`, lower-case kebab, CHECK).

## Time ([ADR 0008](../decisions/0008-storage-conventions.md))
- Instants: `timestamptz`, second precision (sub-seconds truncated), UTC session; Eloquent: `CarbonImmutable`; **every bound instant carries its UTC offset** (`OffsetAwarePostgresGrammar`, models/pivots/query builder alike). Dates without time: `date`.
- Every row-owning table has `created_at`/`updated_at` `NOT NULL DEFAULT now()` (pure link tables don't).
- `NULL` = unknown / not yet, never a magic date.

## Enumerated values
CHECK + PHP backed enum for closed code-known sets; lookup table for data-extensible vocabularies; no native PG enums. `unknown` is a value, not `NULL`. Constraint naming: `<table>_<column>_check`.

## Deletion and retirement
Canonical data: `retired_at`, no soft deletes, `RESTRICT` from children. Structural parts: `CASCADE` from owner. Personal/account data: hard delete, cascade from owner. External id mappings: retire. Audit/sync records: not cascaded. Details: [`indexes-and-constraints.md`](indexes-and-constraints.md#delete-policy).

## JSONB
Only for rarely-queried provider/type-specific data; `NOT NULL DEFAULT '{}'`; `CHECK (jsonb_is_safe_object(col))` = object, ≤ 64 KiB, no credential-looking key at any depth (case-insensitive); never for relations/ids/progress/secrets. Documented shape per column in the dictionary.

## Structural patterns used in the schema

| Pattern | Used for | How it works |
| --- | --- | --- |
| **Same-owner composite FK** | edition ∈ game of the release; release and trophy set of the same game; group ∈ set of the trophy; earned trophy ∈ set ∈ account library | Parent has `UNIQUE (id, owner_id)`; child FK is `(child_ref, owner_id) → parent(id, owner_id)`. Impossible states are rejected by PostgreSQL. |
| **Exclusive arc** | `external_identifiers` (game \| release \| trophy set), later `provenance`, `guides` | one nullable real FK per target kind + `CHECK (num_nonnulls(...) = 1)`; partial indexes per column. No polymorphic FK without integrity. |
| **Ordered pair for symmetric relations** | `*_relations` | `CHECK from < to` for symmetric kinds; `UNIQUE (kind, LEAST(from,to), GREATEST(from,to))` for all kinds; helper `LinksPairs::link()`. |
| **Retire instead of delete** | games, releases, trophy sets, identifier mappings | `retired_at timestamptz NULL`; partial unique indexes use `WHERE retired_at IS NULL`. |
| **Absence = false** | `psn_account_trophies` | earned rows only; "not earned" is the missing row. |
| **Derived, not stored** | completion, platinum status, prequel, progress % | computed by queries/services; no stale caches. |

## Domain concept ≠ table
Not every M0.2 concept became a table: `Edition` is a table, but `CompletionTarget` (system targets), `Recommendation`, `ExportProfile`, `Report`, `Import`, `UserTrophy` (→ account trophies) and `UserTracker` are not. The mapping is in [`logical-model.md`](logical-model.md#m02-concept--m03-table-mapping).

## Code placement ([ADR 0008](../decisions/0008-storage-conventions.md), Q8)
- Eloquent models: `backend/app/Models/<Domain>/` (`Identity`, `Catalog`, `Trophies`, `Sync`, later `Knowledge`, `PlayerProgress`, `PersonalCompletion`, `Planning`). Namespace `App\Models\<Domain>`; factories mirror it in `Database\Factories\<Domain>`.
- PHP enums mirroring CHECK lists: `backend/app/Enums/`. Model concerns: `app/Models/Concerns/`.
- Services/value objects/use cases (none yet): `backend/app/Domain/<Domain>/`, created with their first real class. Provider adapters: `backend/app/Integrations/<Provider>/`.
- Eloquent is used directly; no repository layer. Controllers stay thin; business rules live in services or in the database as constraints.
- Models use `$guarded = []` because input is validated at the boundary (requests/importers), never mass-assigned from raw user input.

## Database safety
Tests use a restricted role and `<db>_test`; destructive artisan commands refuse non-test databases; resets of the dev DB go through `make db-reset-dev CONFIRM=<name>`. See [`database-safety.md`](database-safety.md) and [ADR 0014](../decisions/0014-database-safety.md).

## Migration rules
- One migration per aggregate: a table together with the link tables/seed rows that cannot exist without it; unrelated tables never share a file.
- Reference data is seeded by the migration that creates the table (idempotent per environment, available in every test database).
- Constraints that Blueprint cannot express are raw SQL **in the same migration**, named explicitly.
- Migrations never reference PHP enums or models (they must not change meaning later).
- `down()` must remove exactly what `up()` created. `MigrationLifecycleTest` rolls back **one migration at a time**, checks that nothing of ours is left (tables, indexes, functions), migrates forward again and runs `migrate:fresh` twice — so every `up()` must be re-runnable on a database where only tables were dropped (e.g. `CREATE OR REPLACE FUNCTION`, `CREATE EXTENSION IF NOT EXISTS`).
- Use `DB::unprepared()` for SQL containing `?` (jsonpath filters, JSON operators): PDO would read it as a placeholder.
