# Indexes, constraints and delete policy

Principle: **constraints encode invariants, indexes serve named query patterns.** No blanket indexing; every index below has a reason. Foreign keys on the referencing side are always supported by an index led by the first FK column (tested).

## Query patterns → indexes (physical tables)

| # | Query pattern | Served by |
| --- | --- | --- |
| 1 | Library list of games, sorted by title | `games_sort_title_index` |
| 2 | Game search by substring ("dead red") | `games_title_trgm_idx` (GIN, `lower(title) gin_trgm_ops`, `pg_trgm`) |
| 3 | Game by slug (URL) | `games_slug_unique` |
| 4 | Resolve a provider id to an entity (import hot path) | `external_identifiers_active_unique (provider_code, namespace, value) WHERE retired_at IS NULL` |
| 5 | All external ids of a game / release / set | partial indexes `external_identifiers_game_idx`, `…_game_release_idx`, `…_trophy_set_idx` |
| 6 | All releases / sets / editions of a game | `game_releases_game_id_index`, `trophy_sets_game_id_index`, `editions (game_id, slug)` |
| 7 | Releases on a platform | `game_release_platforms_platform_code_index` (+ PK for the reverse) |
| 8 | Which sets does a release use / which releases use a set | PK `(game_release_id, trophy_set_id)` and `game_release_trophy_sets_trophy_set_id_index` |
| 9 | Groups of a set in order | `trophy_groups (trophy_set_id, ordinal)` |
| 10 | Trophies of a set / group in order | `trophies (trophy_set_id, ordinal)`, `(trophy_group_id, ordinal)` |
| 11 | Relations of a game/set/trophy in both directions | `*_relations`: index on `from_*_id`, on `to_*_id`, plus the unique pair index |
| 12 | Trophy sets in an account's library; accounts holding a set | PK `(psn_account_id, trophy_set_id)`, `psn_account_trophy_sets_trophy_set_id_index` |
| 13 | Earned trophies of an account within one set (completion %) | `psn_account_trophies (psn_account_id, trophy_set_id)` |
| 14 | Recently earned trophies of an account (`earned_at` desc) | `psn_account_trophies (psn_account_id, earned_at)` |
| 15 | Who earned this trophy / does anyone hold it (delete guard) | `psn_account_trophies_trophy_id_index` |
| 16 | A user's accounts | `psn_accounts_user_id_index` |
| 17 | Login by email (case-insensitive) | `users_email_lower_unique` |

Planned patterns (indexes created with their tables): objective subtree and ordering (`objectives (guide_id, parent_id, position)`), guide versions (`(guide_id, version_number)`), user progress by objective/tracker (PK), running run per scope (partial unique), external records by provider/type/key, estimates by user and target.

## Uniqueness (database-enforced)

| Rule | Constraint |
| --- | --- |
| One game slug; one edition slug per game | `games_slug_unique`; `UNIQUE (game_id, slug)` |
| A PSN account id exists once, across all users | `psn_accounts_psn_account_id_unique` |
| An active external id identifies one entity | partial unique index (pattern 4) |
| Group key / trophy key unique within a trophy set | `UNIQUE (trophy_set_id, set_key)` on `trophy_groups`, `trophies` |
| At most one platinum per trophy set | `trophies_one_platinum_per_set` (partial, `WHERE grade = 'platinum'`) |
| One relation per kind and unordered pair; no contradictory directed pairs | `*_relations_pair_unique` on `(kind, LEAST(from,to), GREATEST(from,to))` |
| A pair is `autopop` **or** `partial_autopop` | `trophy_set_relations_autopop_exclusive` (partial) |
| A trophy earned once per account; a set held once per account | PKs of the progress tables |
| Case-insensitive unique email | `users_email_lower_unique` |

## CHECK constraints

- **Enumerations** (closed sets): `trophies.grade`, `trophy_groups.classification`/`classification_origin`, relation `kind`s, `psn_accounts.status`, `providers.kind`. Kept in sync with PHP enums by `EnumConsistencyTest`.
- **Format:** reference `code`s, `slug`s, external-id `namespace` (`^[a-z][a-z0-9_]*$`), non-empty `title`/`name`/`value`/`online_id`.
- **Ranges:** `set_key >= 0`, `ordinal >= 0`, `provider_reported_progress BETWEEN 0 AND 100`.
- **Relations:** `from <> to`; symmetric kinds `from < to`.
- **Exclusive arc:** `num_nonnulls(game_id, game_release_id, trophy_set_id) = 1`.
- **JSONB:** `jsonb_is_safe_object(col)` — object, ≤ 64 KiB, no credential-looking key at any depth, case-insensitive (see [`security-and-performance.md`](security-and-performance.md) for what it cannot detect).
- **Text hygiene:** human text non-empty without leading/trailing whitespace; identifiers (`email`, `psn_account_id`, `online_id`, external-id `value`, group `set_key`) without any whitespace (regex CHECKs; `btrim()` would miss tabs/newlines).
- **Not in CHECKs:** rules that change often or span tables ("a release has ≥ 1 platform", completion rules, precedence between providers) live in application code with tests.

Planned CHECKs with the later tables: `estimate_min >= 0 AND estimate_max >= estimate_min`, `progress_value >= 0`, `target_value > 0`, exclusive-arc targets for guides/provenance.

## Query-shape traps worth knowing
- **Partial index:** `external_identifiers_active_unique` only serves queries that repeat `retired_at IS NULL`. Always resolve identifiers through `ExternalIdentifier::active()`; the same lookup without the predicate cannot use the index (`IndexReviewTest`).
- **Ordered lists** (`trophies`, `trophy_groups` by `ordinal`, recent earned by `earned_at`) are served in index order — no Sort node — only when filtered by the leading column (`trophy_set_id` / `psn_account_id`).
- **Index hygiene is tested:** no index may be a redundant prefix of another or duplicate one; every documented pattern has an index it can use (EXPLAIN with sequential scans disabled on seeded data: *applicability*, not planner preference).

## Known limitations of the constraints
- **Cross-row rules** that PostgreSQL cannot express with keys/CHECKs: `trophy_relations` may link two trophies of the *same* set (`equivalent`/`similar` could be meaningful there; `replaces`/`autopop` within one set is nonsensical but harmless), longer relation cycles (`A remake_of B`, `B remake_of C`, `C remake_of A`) are possible (only pair-level contradictions are blocked), and "a release has ≥ 1 platform" is application-level. Add triggers only if real data shows the problem.
- Name-based JSONB guard cannot see secret values.

## Same-owner composite foreign keys

| Child FK | Parent unique key | Guarantees |
| --- | --- | --- |
| `game_releases (edition_id, game_id)` | `editions (id, game_id)` | edition belongs to the release's game |
| `game_release_trophy_sets (game_release_id, game_id)` | `game_releases (id, game_id)` | release side belongs to `game_id` |
| `game_release_trophy_sets (trophy_set_id, game_id)` | `trophy_sets (id, game_id)` | trophy set side belongs to the same `game_id` |
| `trophies (trophy_group_id, trophy_set_id)` | `trophy_groups (id, trophy_set_id)` | group belongs to the trophy's set |
| `psn_account_trophies (trophy_id, trophy_set_id)` | `trophies (id, trophy_set_id)` | trophy belongs to the stated set |
| `psn_account_trophies (psn_account_id, trophy_set_id)` | `psn_account_trophy_sets (psn_account_id, trophy_set_id)` | the account holds that set |

## Delete policy

| Parent → child | Action | Rationale |
| --- | --- | --- |
| games → editions, releases, trophy sets | **RESTRICT** | canonical data is retired, not deleted |
| editions → releases | RESTRICT | |
| trophy sets → groups, trophies | **CASCADE** | structural parts of the list (but blocked by progress, see below) |
| trophy groups → trophies | CASCADE | |
| trophies → `psn_account_trophies` | **RESTRICT** | never lose someone's earned state by deleting a definition |
| trophy sets → `psn_account_trophy_sets` | RESTRICT | |
| games/releases/sets → `external_identifiers`, relations, link tables | CASCADE | mappings and relations are meaningless without their entity |
| releases → `game_release_platforms`, sets → `trophy_set_platforms` | CASCADE | |
| users → `psn_accounts` | **CASCADE** | personal data follows its owner |
| psn_accounts → `psn_account_trophy_sets` → `psn_account_trophies` | CASCADE | re-syncable account state |
| platforms, regions, providers → anything | RESTRICT | reference data is not deleted |

Consequence: deleting a trophy set that someone has progress on is rejected by PostgreSQL (RESTRICT via the cascade chain) — the operation is "retire", or "delete the accounts' progress first". Retired entities stay referenced.
Planned: audit/sync rows (`import_runs`, `import_errors`, `external_records`) use `SET NULL`/no FK to domain rows and are never removed by domain deletes; their own retention job purges them.

## Time and ordering notes
`timestamptz(0)`; indexes on `earned_at` are btree. UUIDv7 keys are time-ordered, so `created`-order scans over `id` are index-friendly.
