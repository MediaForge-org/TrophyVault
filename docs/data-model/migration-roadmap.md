# Migration roadmap

Rule: **one migration per aggregate** — a table plus link tables/seed rows that cannot exist without it (e.g. `game_releases` + `game_release_platforms`, `trophy_sets` + `trophy_set_platforms`, `completion_targets` + `completion_target_items`). Unrelated tables never share a file; trivial pivots do not get a separate story. Reference data is seeded by the creating migration. Constraints Blueprint cannot express are raw SQL in the same migration. Dates in file names: M0.3 files are `2026_10_07_…`; later files use their creation date.

Milestone slots: **M0.3** (done), **M1** (library on the existing catalog), **M2** (PSN sync — given by the product plan). **M4–M8 are provisional slots** named after the capability (guides/objectives, trackers + personal completion, completion & history, planning, export); numbering will follow the product roadmap.

## Implemented in M0.3 (20 tables, 20 migration files)

| Migration (`2026_10_07_…`) | Table(s) | Domain | Milestone | Depends on | Reason |
| --- | --- | --- | --- | --- | --- |
| `000100_enable_postgres_extensions` | `pg_trgm` extension | — | M0.3 | — | substring title search |
| `000150_create_jsonb_guard_function` | SQL function `jsonb_is_safe_object` | — (cross-cutting) | M0.3 | extension | one definition of "acceptable JSON" for every JSONB CHECK; idempotent (`CREATE OR REPLACE`) |
| `000200_create_providers_table` | `providers` | Sync/Providers | M0.3 | — | FK target of identifiers; seeded reference data |
| `000300_create_platforms_table` | `platforms` | Catalog | M0.3 | — | reference data |
| `000400_create_regions_table` | `regions` | Catalog | M0.3 | — | reference data |
| `000500_create_users_table` | `users` | Identity | M0.3 | — | multi-user anchor |
| `000600_create_psn_accounts_table` | `psn_accounts` | Identity | M0.3 | users | account identity separate from user |
| `000700_create_games_table` | `games` | Catalog | M0.3 | extension | root of the catalog |
| `000800_create_editions_table` | `editions` | Catalog | M0.3 | games | editions are not games |
| `000900_create_game_releases_table` | `game_releases`, `game_release_platforms` | Catalog | M0.3 | games, editions, regions, platforms | releases and their platforms |
| `001000_create_game_relations_table` | `game_relations` | Catalog | M0.3 | games | game graph |
| `001100_create_trophy_sets_table` | `trophy_sets`, `trophy_set_platforms` | Trophies | M0.3 | games, platforms | independent trophy lists |
| `001200_create_game_release_trophy_sets_table` | `game_release_trophy_sets` | Trophies↔Catalog | M0.3 | game_releases, trophy_sets | release ↔ set link, same game |
| `001300_create_external_identifiers_table` | `external_identifiers` | Catalog (x-cutting) | M0.3 | providers, games, game_releases, trophy_sets | provider ids ≠ domain ids |
| `001400_create_trophy_groups_table` | `trophy_groups` | Trophies | M0.3 | trophy_sets | structure + classification |
| `001500_create_trophies_table` | `trophies` | Trophies | M0.3 | trophy_groups | definitions |
| `001600_create_trophy_set_relations_table` | `trophy_set_relations` | Trophies | M0.3 | trophy_sets | stacks, autopop |
| `001700_create_trophy_relations_table` | `trophy_relations` | Trophies | M0.3 | trophies | equivalence between trophies |
| `001800_create_psn_account_trophy_sets_table` | `psn_account_trophy_sets` | Player progress | M0.3 | psn_accounts, trophy_sets | account library |
| `001900_create_psn_account_trophies_table` | `psn_account_trophies` | Player progress | M0.3 | trophies, psn_account_trophy_sets | earned state |

Why these now: they define the platform's structure and the ownership of progress; every later milestone (library, sync, guides) depends on them, and each could be validated against constraints and scenarios without any feature code. Not now: tables without a first consumer (below).

## Planned (24 tables, ~20 migration files)

| Migration / tables | Domain | Milestone slot | Depends on | Reason for timing |
| --- | --- | --- | --- | --- |
| `user_games` | Player progress | M1 | users, games | library/backlog intent; first user-facing personal data |
| `psn_account_credentials` | Identity (secret) | M2 | psn_accounts | needed with PSN authentication, encrypted |
| `trophy_stats` | Trophies | M2 | trophies, providers | provider rarity arrives with the first sync |
| `sources` | Provenance | M2 | providers | first importer cites sources |
| `import_runs` (+ `import_errors`) | Sync | M2 | providers, psn_accounts | run history, retries |
| `external_records` | Sync | M2 | providers, sources, import_runs | change detection for the first import |
| `provenance` | Provenance | M2 | external_records, sources, games, trophy_sets, … | after first normalised rows exist |
| `guides` + `guide_versions` | Knowledge | M4 | games, trophy_sets, trophies | guide content |
| `objective_types` | Knowledge | M4 | — | lookup, seeded |
| `objectives` | Knowledge | M4 | guides, objective_types, guide_versions | recursive tree (+ deferrable order, cycle trigger) |
| `objective_relations` | Knowledge | M4 | objectives, trophies | `satisfies`/`requires` |
| `guide_time_estimates` | Knowledge | M4 | guide_versions | baseline estimates |
| `tracker_definitions` | Knowledge | M5 | guides, objectives | data-driven trackers |
| `user_objective_progress` | Personal completion | M5 | users, objectives | personal objective state |
| `user_tracker_progress` | Personal completion | M5 | users, tracker_definitions | counters/values |
| `progress_snapshots` | Player progress | deferred (M6+) | psn_accounts, import_runs | only when a feature needs non-derivable history |
| `estimates` (optional) | Planning | M7 | users | cache only if on-demand is too slow |
| `completion_targets` + `completion_target_items` | Planning | M7 | users, objectives, trophy_sets | custom targets |
| `completion_plans` + `completion_plan_items` | Planning | M7 | users | plans |
| `export_runs` (optional) | Export | M8 | users | history of exports |

## Counts
- **Added now:** 20 migration files / 20 tables (19 table/extension migrations + the JSONB guard function; plus the earlier `failed_jobs`).
- **Planned for M1:** 1 (`user_games`). **M2:** ~6 files (credentials, stats, sources, external_records, import_runs+errors, provenance).
- **Roughly expected for v1:** ~40 domain migration files, **44 tables** (+ Laravel's `migrations`, `failed_jobs`). The number follows the grouping rule, not a target.

## Rules for adding migrations
1. Update `logical-model.md`, `er-diagrams.md`, this roadmap and (after migrating) regenerate `data-dictionary.md` with `python3 scripts/generate-data-dictionary.py` (add the prose entry first) — `DocumentationConsistencyTest` checks tables and columns.
2. Add tests for every new constraint (unique, FK, CHECK, cascade/restrict) in `backend/tests/Integration/`.
3. Never edit a migration that has been committed and pushed; add a new one. (M0.3's files were adjusted freely before their first commit.)
