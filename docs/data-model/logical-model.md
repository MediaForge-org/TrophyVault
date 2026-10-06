# Logical data model v1

Complete v1 model, by domain. **Impl.** = migrated in M0.3 ✔, or the milestone slot that introduces it (see [`migration-roadmap.md`](migration-roadmap.md); milestones after M2 are provisional slots, not commitments).
Column details of migrated tables: [`data-dictionary.md`](data-dictionary.md). Diagrams: [`er-diagrams.md`](er-diagrams.md).

## Identity

| Table | Impl. | Purpose / key columns |
| --- | --- | --- |
| `users` | ✔ | local user: `name`, `email` (unique, case-insensitive), `password`, `remember_token` |
| `psn_accounts` | ✔ | `user_id` → users, `psn_account_id` (unique), `online_id`, `status`, `last_synced_at`, `profile jsonb` (non-secret) |
| `psn_account_credentials` | M2 | `psn_account_id` (PK, FK), encrypted token payload (Laravel `encrypted` cast), `expires_at`; **secret**, never exported/logged |

## Catalog (canonical)

| Table | Impl. | Purpose / key columns |
| --- | --- | --- |
| `platforms`, `regions` | ✔ | lookup tables (`code` PK) |
| `games` | ✔ | `slug`, `title`, `sort_title`, `retired_at`, `metadata jsonb` |
| `editions` | ✔ | `game_id`, `slug`, `name` |
| `game_releases` | ✔ | `game_id`, `edition_id?` (same game), `region_code`, `title?`, `release_date?`, `retired_at`, `metadata` |
| `game_release_platforms` | ✔ | n:m release ↔ platform |
| `game_relations` | ✔ | directed/symmetric relation between games |
| `external_identifiers` | ✔ | `(provider, namespace, value)` → game \| release \| trophy set (exclusive arc) |

## Trophies (canonical)

| Table | Impl. | Purpose / key columns |
| --- | --- | --- |
| `trophy_sets` | ✔ | `game_id` (exactly one game), `name`, `retired_at`, `metadata` |
| `trophy_set_platforms` | ✔ | n:m set ↔ platform |
| `game_release_trophy_sets` | ✔ | n:m release ↔ set, same-game guaranteed |
| `trophy_groups` | ✔ | `set_key`, `name`, `ordinal`, `classification` (+`_origin`) |
| `trophies` | ✔ | `set_key`, `grade`, `name`, `description`, `is_hidden`, `ordinal`, `metadata`; ≤ 1 platinum per set |
| `trophy_set_relations` | ✔ | stacks/equivalence (symmetric), autopop/partial (directed) |
| `trophy_relations` | ✔ | equivalent/similar/related (symmetric), replaces/autopop (directed) |
| `trophy_stats` | M2 | `(trophy_id, provider_code)` PK, `earned_rate numeric(5,2)`, `observed_at` — provider rarity, time-varying, not on `trophies` |

## Player progress (personal; account-owned for PSN data)

| Table | Impl. | Purpose / key columns |
| --- | --- | --- |
| `psn_account_trophy_sets` | ✔ | account has this list; `first_seen_at`, `last_synced_at`, `provider_reported_progress` |
| `psn_account_trophies` | ✔ | earned trophies only; `earned_at` (nullable = unknown) |
| `user_games` | M1 | PK `(user_id, game_id)`: `intent` (`not_started|backlog|playing|paused|abandoned`), `started_at`, `favorite`, `notes`. **No achievement state** (derived). |
| `progress_snapshots` | deferred (M6+) | `(psn_account_id, trophy_set_id?, taken_at, counts…, import_run_id)`; only for non-derivable history; see [ADR 0012](../decisions/0012-account-owned-progress.md) |

## Sync / providers / provenance (cross-cutting; [ADR 0010](../decisions/0010-provenance-and-sync-model.md))

| Table | Impl. | Purpose |
| --- | --- | --- |
| `providers` | ✔ | reference rows (psn, psnprofiles, powerpyx, playstationtrophies, community, manual) |
| `sources` | M2 | citable origin (url, kind, license) |
| `external_records` | M2 | provider-returned records, content hash, retention of payload |
| `import_runs` | M2 | one run model for sync/import/backfill; one running run per scope |
| `import_errors` | M2 | per-run problems (sanitised) |
| `provenance` | M2 | exclusive-arc link from normalised rows to record/source |

## Knowledge ([ADR 0013](../decisions/0013-objectives-trackers-guides.md))

| Table | Impl. | Purpose |
| --- | --- | --- |
| `guides` | M4 | guide with exclusive-arc target (game \| trophy set \| trophy \| objective) |
| `guide_versions` | M4 | immutable versions (markdown body, hash) |
| `objective_types` | M4 | lookup (`code` PK), data-extensible |
| `objectives` | M4 | recursive tree (adjacency list), `stable_key`, `type_code`, `position`, version introduced/retired |
| `objective_relations` | M4 | `satisfies` (→ trophy), `relates_to`, `requires` |
| `guide_time_estimates` | M4 | knowledge baseline estimates |
| `tracker_definitions` | M5 | data-driven trackers, closed strategy registry |

## Personal completion

| Table | Impl. | Purpose |
| --- | --- | --- |
| `user_objective_progress` | M5 | PK `(user_id, objective_id)`: state, numeric progress, `completed_at`, note, manual override |
| `user_tracker_progress` | M5 | PK `(user_id, tracker_definition_id)`: value (merged `UserTracker` + progress) |

## Planning and export

| Table | Impl. | Purpose |
| --- | --- | --- |
| `estimates` | M7, optional | cache/history of personal estimates (min/max/confidence/method/`calculated_at`/`inputs jsonb`) — computing on demand is the default |
| `completion_targets`, `completion_target_items` | M7 | **custom** user targets and their members (system targets are an enum) |
| `completion_plans`, `completion_plan_items` | M7 | user plans (ordered subjects) |
| `export_runs` | M8, optional | history of produced exports (profile, schema version, file ref) |

**Not tables:** `Recommendation` (computed), `ExportProfile` (versioned code/config), `Report` (rendering), system `CompletionTarget`s (enum).

Totals: **20 physical tables in M0.3**, 24 planned → **44 tables** in v1 (+ Laravel's `migrations`, `failed_jobs`).

## M0.2 concept → M0.3 table mapping

| M0.2 concept | M0.3 result |
| --- | --- |
| User, PsnAccount | `users`, `psn_accounts` |
| Game, GameRelease, Edition, Platform, Region, GameRelation, ExternalIdentifier | same-named tables (`game_releases`, …); release↔platform n:m `game_release_platforms` |
| TrophySet, TrophyGroup, Trophy, TrophySetRelation, TrophyRelation | same-named tables; plus `trophy_set_platforms`, `game_release_trophy_sets` |
| UserGame | `user_games` (M1), lifecycle intent only |
| UserTrophySet, UserTrophy | **superseded** → `psn_account_trophy_sets`, `psn_account_trophies` ([ADR 0012](../decisions/0012-account-owned-progress.md)) |
| ProgressSnapshot | deferred table `progress_snapshots` |
| Guide, GuideVersion, Objective, ObjectiveRelation, TrackerDefinition | `guides`, `guide_versions`, `objectives`, `objective_relations`, `tracker_definitions` (+ `objective_types`) |
| Provenance / Source | **moved** out of Knowledge → cross-cutting `sources` + `provenance` ([ADR 0010](../decisions/0010-provenance-and-sync-model.md)) |
| UserObjectiveProgress | `user_objective_progress` |
| UserTracker + UserTrackerProgress | **merged** → `user_tracker_progress` |
| Provider | `providers` |
| Import + ImportRun + SyncRun | **merged** → `import_runs` (`run_type`) |
| ExternalRecord, SyncError | `external_records`, `import_errors` |
| Estimate | `estimates` (optional cache) + `guide_time_estimates` (knowledge) |
| CompletionTarget | enum (system) + `completion_targets` (custom) |
| CompletionPlan | `completion_plans` (+items) |
| Recommendation | computed, no table |
| Export, ExportProfile, Report | optional `export_runs`; profile/report are code |
