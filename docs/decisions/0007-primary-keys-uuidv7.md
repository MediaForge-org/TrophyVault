# 0007 — Primary keys: UUIDv7 everywhere

Status: accepted · Date: 2026-10-07 · Resolves M0.3 open question 1

## Context
TrophyVault needs identifiers that are (a) independent of any provider ([ADR 0002](0002-providers-do-not-define-the-domain.md)),
(b) stable in exports and later offline "guide packs" / data packs that are generated outside the database, (c) cheap to index on
PostgreSQL 18 with hundreds of thousands of trophy rows, and (d) simple. Options evaluated:

| Option | For | Against |
| --- | --- | --- |
| `bigint` identity | smallest, fastest joins, easy to read | not portable between installations/exports (collisions when merging packs), guessable, ids can only be assigned by the database |
| UUIDv4 | portable, generated anywhere | random → poor index locality, page splits, larger WAL |
| **UUIDv7** | portable, generated anywhere (also offline), **time-ordered → good locality**, native `uuidv7()` in PostgreSQL 18, Laravel 12+ `HasUuids` generates v7 | 16 bytes instead of 8 |
| ULID | sortable, short text form | no native PostgreSQL type (text/uuid cast), no advantage over v7 now that both Laravel and PostgreSQL speak v7 |
| bigint internal + UUID public | best of both on paper | two ids per row, mapping everywhere, the "double-ID complexity" we want to avoid |

## Decision
1. **Every domain table with a single surrogate key uses a `uuid` primary key holding a UUIDv7**, column name `id`.
   - Database default `uuidv7()` (PostgreSQL 18 built-in) so inserts that bypass Eloquent (SQL, data packs) also get a valid id.
   - Eloquent generates the id client-side (`HasUuids`, trait `HasUuidPrimaryKey`), so the id is known before the insert.
2. **One id per row, no separate public id.** The UUID is safe to expose in URLs and exports. Human-friendly handles are separate columns (`games.slug`).
3. **Exceptions (documented, tested in `SchemaConventionsTest`):**
   - Reference tables with a stable natural code: `providers`, `platforms`, `regions` → `code text` primary key (`ps5`, `psn`, `global`).
   - Pure link/state tables without attributes of their own use their natural composite key: `game_release_platforms`,
     `trophy_set_platforms`, `game_release_trophy_sets`, `psn_account_trophy_sets`, `psn_account_trophies`.
4. **Provider identifiers are never primary keys** (see [ADR 0009](0009-external-identifiers.md)).
5. Foreign keys to uuid keys are `uuid`; composite "same-owner" foreign keys are described in [`conventions.md`](../data-model/conventions.md).

## Consequences
- Joins use 16-byte keys: roughly 2× index size for key columns versus bigint. Acceptable at the expected scale (see [`security-and-performance.md`](../data-model/security-and-performance.md)).
- ids are time-ordered, so range/created-order scans are index-friendly; but **the id leaks creation time** — fine for a self-hosted product, not a secret.
- UUIDs are lower-case hex; ordered-pair relations rely on `from_id < to_id` comparing identically in PHP (`strcmp` of lower-case hex, input is lower-cased first) and PostgreSQL (byte order) — covered by `UuidTest`. Malformed ids in route binding are rejected by Laravel before they reach PostgreSQL (clean `ModelNotFoundException`/404).
- Data packs/exports can carry ids and be merged without renumbering.
