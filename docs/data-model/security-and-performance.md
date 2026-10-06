# Security and performance review of the data model

## Data classes (extends [`../architecture/data-classification.md`](../architecture/data-classification.md))

| Class | Meaning | Tables / data | Handling |
| --- | --- | --- | --- |
| **Public / canonical** | objective facts, shareable in principle | `games`, `editions`, `game_releases`, platforms/regions, trophy structure, relations, `external_identifiers` | may be exported/shared; contains **no** third-party guide text |
| **Local / private** | personal to this installation | `users` (email, password hash), `psn_accounts` (online id, profile), account progress, `user_*` tables, notes, estimates, plans | per-user; exported only with that user's consent; deleted with the owner |
| **Local, third-party content** | imported guide knowledge with a source/licence | `guides`, `guide_versions`, `objectives` (when imported), `sources.content_license` | local database only; **never committed to the repository**; removable per source via provenance |
| **Operational** | traces of external communication | `import_runs`, `import_errors`, `external_records`, `provenance` | retention-limited; payloads redacted |
| **Secret** | gives access to an account/system | PSN credentials/tokens/cookies (`psn_account_credentials`, M2), `APP_KEY`, DB/Redis passwords, `users.password` (hash only) | encrypted at rest in dedicated tables/env only; excluded from logs, errors, exports, JSONB |

## Rules
1. **No secrets in domain columns or JSONB.** Enforced defensively by `CHECK (jsonb_is_safe_object(col))` on every JSONB column: object only, ≤ 64 KiB, and **no credential-looking key at any depth** (nested objects and arrays, case-insensitive: `token`, `access_token`, `refresh_token`, `id_token`, `npsso`, `cookie(s)`, `password`, `secret`, `client_secret`, `api_key`, `authorization`, `bearer`, …; `JsonbGuardTest`). **Limits, stated plainly:** it matches key *names* only — a secret in a *value* (`{"url": "https://x?token=abc"}`), a disguised key (`tkn`), or a string-encoded JSON blob passes. It is a safety net against accidents, not a secret store and not a scanner; the real control is that secrets have their own tables and adapters never put credentials into metadata.
2. **PSN credentials** (planned `psn_account_credentials`): one row per account, Laravel `encrypted` cast (app key), `$hidden` on the model, never selected by list queries, never joined into exports. NPSSO/refresh tokens are treated as passwords. Rotation = overwrite the row.
3. **`users.password`** stores only a bcrypt/argon hash (`hashed` cast); `remember_token` is hidden.
4. **`psn_accounts.profile`** is public-profile data only (avatar URL, language). It is still "local/private" because it identifies a person.
5. **Raw payloads** (`external_records.payload`) are redacted by the adapter before storage, bounded by retention, and never exported. `import_errors.message/context` are sanitised (no request headers, no tokens).
6. **Personal notes** (`user_games.notes`, `user_objective_progress.note`) are plain text owned by the user; they are part of that user's export only.
7. **Export:** profiles list entities explicitly (allow-list); secrets and operational tables are not exportable by construction. Exports carry ids (UUIDv7) and provenance, not credentials.
8. **Imported guide content:** each source carries `content_license`; local database only, with provenance so a source's content can be purged without touching code or personal data. Repository tests use synthetic fixtures.
9. **Authorisation** is not modelled in the schema beyond ownership keys (`user_id` on personal rows, account → user). Every personal query must be scoped by the authenticated user (tested with the feature that adds it). The Horizon gate remains closed outside `local` ([ADR 0001](../decisions/0001-foundation.md)).
10. **Referential integrity as security:** composite FKs prevent cross-owner mixups (a release cannot point at another game's trophy set; an account cannot have earned a trophy of a list it does not hold).

## Database access safety (new in the M0.3 hardening)
- Tests run as a **restricted PostgreSQL role** without access to the development database; destructive artisan commands refuse non-test databases; development resets are explicit and named. Details: [`database-safety.md`](database-safety.md), [ADR 0014](../decisions/0014-database-safety.md).
- Two further guards against mistakes in identifiers: whitespace-free identifiers (a tab/newline can no longer create a second spelling of an external id or e-mail) and the active-identifier partial unique index.
- `psn_accounts.profile`, `*.metadata` may hold provider data, **never credentials**; PSN credentials get an encrypted, hidden, non-exported table (M2).

## Performance review

Expected order of magnitude on a self-hosted PostgreSQL 18 instance:

| Entity | Estimate | Note |
| --- | --- | --- |
| games / releases | 10⁴ / 3·10⁴ | trivial |
| trophy sets / groups | 10⁴ / 3·10⁴ | |
| trophies | ~5·10⁵ (≈ 50 per set) | largest canonical table; 16-byte keys ≈ 8 MB per key index |
| earned rows per PSN account | up to ~5·10⁴ | `psn_account_trophies`; ×few accounts/users |
| objectives (planned) | 10⁵–10⁶ across guides | adjacency list + recursive CTE is sufficient (subtrees ≤ a few thousand) |
| sync history (planned) | grows linearly with runs | retention purges payloads; runs/errors stay small |

Findings:
- **No N+1 forced by the model:** every to-many access has a covering index (patterns in [`indexes-and-constraints.md`](indexes-and-constraints.md)); relationships are one-hop (`game → sets`, `set → groups → trophies`, `account → earned`). Eager loading is supported by plain FKs.
- **Completion** for one set = two indexed counts (`trophies` by set, earned by `(account, set)`); for a library page, one grouped query over `psn_account_trophies (psn_account_id, trophy_set_id)`. No cache column exists to go stale; a rebuildable summary table is the planned remedy *if measured* too slow.
- **UUIDv7** keeps insert locality for the large tables; the cost is 2× key width vs bigint — acceptable at this scale (see [ADR 0007](../decisions/0007-primary-keys-uuidv7.md)).
- **Writes:** a full sync of an account upserts ≤ ~5·10⁴ earned rows in chunks; PK `(psn_account_id, trophy_id)` supports `INSERT … ON CONFLICT`. Indexes on `psn_account_trophies` are limited to four (PK + three) to bound write amplification.
- **Search:** `pg_trgm` GIN index only on `games.title` (the one substring-search column); no full-text engine in v1.
- **No partitioning/sharding.** Revisit only with measured need (e.g. > 10⁸ rows in a table).
- **Known pressure points** (documented, not solved): `trophies` metadata JSONB size if raw provider objects are stuffed in (policy: don't); global "all earned trophies of a user across accounts" needs a join over accounts (indexed).
