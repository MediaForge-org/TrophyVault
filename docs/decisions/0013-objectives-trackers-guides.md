# 0013 — Objectives, trackers, guides: persistence design

Status: accepted (logical v1; tables arrive in M4/M5) · Date: 2026-10-07 · Resolves M0.3 open questions 6, 22, 23, 24, 25, 26 (builds on [ADR 0005](0005-recursive-objectives.md))

## Decision

### Objective tree: adjacency list + recursive CTE
Options: adjacency list (`parent_id`), closure table, materialised path/`ltree`, nested sets. Trees are small to medium (hundreds to a few thousand nodes per guide), read far more than they are re-parented, and PostgreSQL has recursive CTEs.
**Adjacency list with a composite FK** `(parent_id, guide_id) → objectives(id, guide_id)` (a parent must be in the same guide), `position integer` for sibling order with `UNIQUE NULLS NOT DISTINCT (guide_id, parent_id, position) DEFERRABLE INITIALLY DEFERRED` (PostgreSQL 15+; allows reordering inside one transaction). Subtree/ancestor queries use `WITH RECURSIVE`. Cycles are prevented by a `BEFORE INSERT OR UPDATE OF parent_id` trigger (ancestor check) added with the table; a recursive-CTE test is part of that migration's tests. Closure tables are the documented escape hatch if profiling ever demands it.

### Objective rows
`objectives(id, guide_id, parent_id, position, stable_key, type_code → objective_types, title, description, is_optional, spoiler_level, introduced_in_version_id, retired_in_version_id, metadata jsonb)`.
- `stable_key` (`UNIQUE (guide_id, stable_key)`) is the identity used by re-imports of the same guide pack (idempotent), independent of titles.
- **Objective types (Q23): lookup table `objective_types(code, name, …)`**, data-extensible (guide packs may add types), seeded with the M0.2 list (`trophy, mission, collectible, challenge, animal, location, activity, requirement, counter, checklist, chapter, category, custom`). Unknown types render generically. No table per type; type-specific optional settings live in `metadata jsonb` (not queried), anything filtered becomes a column.
- A trophy objective references its trophy through `objective_relations`, not an extra FK column (see below).

### Guides and versions (Q24)
- `guides(id, scope target, slug, title, retired_at, …)` with an **exclusive-arc target**: `game_id | trophy_set_id | trophy_id | objective_id` (exactly one, real FKs, [ADR 0009](0009-external-identifiers.md) pattern) — game guide, trophy-set guide, trophy guide, objective guide; the objective tree itself belongs to one guide (`objectives.guide_id`).
- `guide_versions(id, guide_id, version_number, body (markdown), content_hash, change_note, created_at)` — **immutable** (no updates; application + database trigger), `UNIQUE (guide_id, version_number)`. The current version is the highest number.
- **Granularity:** versions are *guide-level*. Tree history is reconstructable because objectives carry `introduced_in_version_id`/`retired_in_version_id`; **edits to an objective's title/description are not historised in v1** (known limitation). Personal progress references stable objective ids and survives versions.
- Guide content is Knowledge data with provenance (`sources`, `content_license`). Imported third-party text is local-only data, never committed to the repository.

### Objective relations (non-hierarchical)
`objective_relations(from_objective_id, to_objective_id | trophy_id, kind)` with kinds `satisfies` (objective → trophy), `relates_to`, `requires` (prerequisite), `alternative_to`, `blocks`. Same ordered-pair/uniqueness rules as [ADR 0011](0011-catalog-and-trophy-structure.md). v1 ships `satisfies`, `relates_to`, `requires`; the others are reserved names, not built until needed.

### Trackers (Q22)
- `tracker_definitions(id, guide_id, key, kind, title, unit, target_value numeric CHECK > 0, objective_id NULL, strategy, config jsonb)`; kinds `boolean | counter | percentage | checklist | hierarchical | derived` (CHECK + enum).
- **A tracker is a *view/aggregation definition* over objectives, not a second tree.** `checklist`/`hierarchical`/`derived` trackers have `objective_id` (the subtree root, or none for a pure derived tracker) and compute from objective progress. Only `boolean`/`counter`/`percentage` trackers that are *not* backed by objectives store a value of their own.
- **No executable logic in the database.** `derived` trackers choose a `strategy` from a **closed, code-defined registry** (`sum_children`, `count_completed_children`, `percentage_of_children`, `ratio_of_trackers`, …); `config` holds parameters validated against that strategy's schema. New strategies need code; new trackers/games do not.

### Personal completion tables (Q22 optimisation)
- `user_objective_progress (user_id, objective_id)` PK: `state` (`not_started|in_progress|completed|skipped|not_applicable`), `progress_value numeric NULL CHECK ≥ 0`, `completed_at`, `note`, `is_manual_override`. Boolean completion is not enough (skip/not applicable/partial counters are real).
- `user_tracker_progress (user_id, tracker_definition_id)` PK: `value numeric NOT NULL CHECK ≥ 0`, `updated_at` — **M0.2's `UserTracker` + `UserTrackerProgress` are merged** (the instance has no data of its own beyond progress; "activated" = row exists).
- **Responsibility:** objective-backed counters live in `user_objective_progress`; trackers read them. `user_tracker_progress` exists only for objective-less trackers. A CHECK/trigger-free rule: exactly one place stores a given number (enforced in the tracker service and tested then).
- Typed columns (no EAV, no JSONB for values, no per-kind tables).

### Full completion (Q25)
The *default* "TrophyVault Full Completion" is a **system-defined rule in code** (all required trophy sets/groups per [ADR 0012](0012-account-owned-progress.md) complete **and** all non-optional ingame objectives of the game's primary guide completed). Users can add **custom targets** (`completion_targets` + `completion_target_items`, M7) listing concrete sets/groups/objectives/trackers; system targets are an enum, not rows.

### Estimates (Q26)
Knowledge-level baselines (“~20 h for Platinum”) are stored with the guide (`guide_time_estimates`: `guide_version_id`, `target_kind`, `min_minutes`, `max_minutes`, `confidence`, `method`, `source_id`). **Personal remaining-time estimates are computed on demand**; an optional cache `estimates (user_id, subject arc, target_kind, min/max, confidence, method, calculated_at, inputs jsonb)` is added in the planning milestone only if needed. `CHECK (min >= 0 AND max >= min)`.

## Consequences
- Several constraints (deferrable ordering, cycle trigger, immutability trigger) are raw SQL in the M4 migrations; they are specified here and need tests with those tables.
- Objective text edits are not versioned in v1.
- The strategy registry is the safe boundary between "data-driven" and "code".
