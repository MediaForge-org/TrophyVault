# ER diagrams

Mermaid (text-based; renders on GitHub). Each diagram is one domain; cross-domain references are shown as entities with only their key. `PK`/`FK`/`UK` as usual.
Entities marked *(planned)* in the section text are not migrated yet. Without a render tool, read the relationship lines: `A ||--o{ B` = one A has zero-or-more B.

## 1. Identity and player progress (physical)

```mermaid
erDiagram
    users ||--o{ psn_accounts : "owns (cascade)"
    psn_accounts ||--o{ psn_account_trophy_sets : "has list (cascade)"
    trophy_sets ||--o{ psn_account_trophy_sets : "held by (restrict)"
    psn_account_trophy_sets ||--o{ psn_account_trophies : "earned within (cascade)"
    trophies ||--o{ psn_account_trophies : "earned as (restrict)"

    users {
        uuid id PK
        text email UK "lower(email)"
        text password
    }
    psn_accounts {
        uuid id PK
        uuid user_id FK
        text psn_account_id UK
        text online_id
        text status "active|disabled"
        jsonb profile "non-secret"
    }
    psn_account_trophy_sets {
        uuid psn_account_id PK,FK
        uuid trophy_set_id PK,FK
        timestamptz first_seen_at
        smallint provider_reported_progress
    }
    psn_account_trophies {
        uuid psn_account_id PK,FK
        uuid trophy_id PK,FK
        uuid trophy_set_id FK "same set as trophy and account list"
        timestamptz earned_at "NULL = unknown time"
    }
    trophies {
        uuid id PK
        uuid trophy_set_id
    }
    trophy_sets {
        uuid id PK
    }
```

## 2. Catalog (physical)

```mermaid
erDiagram
    games ||--o{ editions : "has"
    games ||--o{ game_releases : "released as (restrict)"
    editions |o--o{ game_releases : "edition of same game"
    regions ||--o{ game_releases : "in region"
    game_releases ||--o{ game_release_platforms : "on"
    platforms ||--o{ game_release_platforms : "hosts"
    games ||--o{ game_relations : "from"
    games ||--o{ game_relations : "to"
    providers ||--o{ external_identifiers : "issued by"
    games |o--o{ external_identifiers : "identified by"
    game_releases |o--o{ external_identifiers : "identified by"
    trophy_sets |o--o{ external_identifiers : "identified by"

    games {
        uuid id PK
        text slug UK
        text title
        text sort_title
        timestamptz retired_at
        jsonb metadata
    }
    editions {
        uuid id PK
        uuid game_id FK
        text slug "UK with game_id"
        text name
    }
    game_releases {
        uuid id PK
        uuid game_id FK
        uuid edition_id FK "composite with game_id"
        text region_code FK
        date release_date
        timestamptz retired_at
    }
    game_release_platforms {
        uuid game_release_id PK,FK
        text platform_code PK,FK
    }
    game_relations {
        uuid id PK
        uuid from_game_id FK
        uuid to_game_id FK
        text kind "remake_of|remaster_of|sequel_to|collection_contains|related"
    }
    external_identifiers {
        uuid id PK
        text provider_code FK
        text namespace
        text value "active (provider,namespace,value) unique"
        uuid game_id FK "exactly one of the three"
        uuid game_release_id FK
        uuid trophy_set_id FK
        timestamptz retired_at
    }
    platforms {
        text code PK
    }
    regions {
        text code PK
    }
    providers {
        text code PK
        text kind
    }
    trophy_sets {
        uuid id PK
    }
```

## 3. Trophy structure and its link to the catalog (physical)

```mermaid
erDiagram
    games ||--o{ trophy_sets : "has lists (restrict)"
    trophy_sets ||--o{ trophy_set_platforms : "labelled"
    platforms ||--o{ trophy_set_platforms : "labels"
    trophy_sets ||--o{ trophy_groups : "structured into (cascade)"
    trophy_groups ||--o{ trophies : "contains (same set)"
    trophy_sets ||--o{ trophies : "owns"
    trophy_sets ||--o{ trophy_set_relations : "from"
    trophy_sets ||--o{ trophy_set_relations : "to"
    trophies ||--o{ trophy_relations : "from"
    trophies ||--o{ trophy_relations : "to"
    game_releases ||--o{ game_release_trophy_sets : "uses"
    trophy_sets ||--o{ game_release_trophy_sets : "used by"

    trophy_sets {
        uuid id PK
        uuid game_id FK
        text name
        timestamptz retired_at
    }
    trophy_set_platforms {
        uuid trophy_set_id PK,FK
        text platform_code PK,FK
    }
    game_release_trophy_sets {
        uuid game_release_id PK,FK
        uuid trophy_set_id PK,FK
        uuid game_id FK "both FKs are (id, game_id): same game"
    }
    trophy_groups {
        uuid id PK
        uuid trophy_set_id FK
        text set_key "UK with set"
        int ordinal
        text classification "base|dlc|expansion|free_update|online|challenge|other|unknown"
        text classification_origin "auto|manual"
    }
    trophies {
        uuid id PK
        uuid trophy_set_id FK
        uuid trophy_group_id FK "composite with trophy_set_id"
        int set_key "UK with set"
        text grade "bronze|silver|gold|platinum (max 1 platinum per set)"
        boolean is_hidden
    }
    trophy_set_relations {
        uuid id PK
        uuid from_trophy_set_id FK
        uuid to_trophy_set_id FK
        text kind "platform_stack|regional_stack|remaster_stack|equivalent|related|autopop|partial_autopop"
    }
    trophy_relations {
        uuid id PK
        uuid from_trophy_id FK
        uuid to_trophy_id FK
        text kind "equivalent|similar|related|replaces|autopop"
    }
    games {
        uuid id PK
    }
    game_releases {
        uuid id PK
    }
    platforms {
        text code PK
    }
```

## 4. Provenance and sync (planned, M2 — [ADR 0010](../decisions/0010-provenance-and-sync-model.md))

```mermaid
erDiagram
    providers ||--o{ sources : "publishes"
    providers ||--o{ external_records : "returned"
    providers ||--o{ import_runs : "run against"
    import_runs ||--o{ import_errors : "reports"
    import_runs ||--o{ external_records : "last seen in"
    external_records ||--o{ provenance : "source of"
    sources ||--o{ provenance : "cites"
    provenance }o--o| games : "subject (exclusive arc)"
    provenance }o--o| trophy_sets : "subject (exclusive arc)"
    provenance }o--o| guide_versions : "subject (exclusive arc)"
    psn_accounts |o--o{ import_runs : "scope"

    import_runs {
        uuid id PK
        text provider_code FK
        text run_type "sync|import|backfill"
        text scope_key "one running run per provider and scope"
        text status
        uuid retry_of_run_id FK
        jsonb stats
    }
    external_records {
        uuid id PK
        text provider_code FK
        text record_type
        text external_key "UK with provider and type"
        text content_hash
        timestamptz retrieved_at
        jsonb payload "bounded, purged by retention"
    }
    provenance {
        uuid id PK
        uuid external_record_id FK
        uuid source_id FK
        text role "imported|curated|confirmed"
    }
    sources {
        uuid id PK
        text url
        text content_license
    }
```

## 5. Knowledge and personal completion (planned, M4/M5 — [ADR 0013](../decisions/0013-objectives-trackers-guides.md))

```mermaid
erDiagram
    guides ||--o{ guide_versions : "versioned (immutable)"
    guides ||--o{ objectives : "owns tree"
    objectives |o--o{ objectives : "parent (same guide)"
    objective_types ||--o{ objectives : "typed"
    objectives ||--o{ objective_relations : "from"
    trophies |o--o{ objective_relations : "satisfied trophy"
    guides ||--o{ tracker_definitions : "defines"
    objectives |o--o{ tracker_definitions : "subtree root"
    guide_versions ||--o{ guide_time_estimates : "baseline"
    users ||--o{ user_objective_progress : "progress"
    objectives ||--o{ user_objective_progress : "of"
    users ||--o{ user_tracker_progress : "progress"
    tracker_definitions ||--o{ user_tracker_progress : "of"
    users ||--o{ user_games : "intent"
    games ||--o{ user_games : "tracked"

    guides {
        uuid id PK
        uuid game_id FK "exactly one target"
        uuid trophy_set_id FK
        uuid trophy_id FK
        uuid objective_id FK
    }
    objectives {
        uuid id PK
        uuid guide_id FK
        uuid parent_id FK "composite with guide_id"
        int position
        text stable_key "UK with guide"
        text type_code FK
        uuid introduced_in_version_id FK
        uuid retired_in_version_id FK
        jsonb metadata
    }
    tracker_definitions {
        uuid id PK
        text kind "boolean|counter|percentage|checklist|hierarchical|derived"
        text strategy "closed code registry"
        numeric target_value
        jsonb config
    }
    user_objective_progress {
        uuid user_id PK,FK
        uuid objective_id PK,FK
        text state "not_started|in_progress|completed|skipped|not_applicable"
        numeric progress_value
        timestamptz completed_at
    }
    user_tracker_progress {
        uuid user_id PK,FK
        uuid tracker_definition_id PK,FK
        numeric value
    }
    user_games {
        uuid user_id PK,FK
        uuid game_id PK,FK
        text intent "not_started|backlog|playing|paused|abandoned"
    }
```

## 6. Planning and export (planned, M7/M8)

```mermaid
erDiagram
    users ||--o{ completion_targets : "custom targets"
    completion_targets ||--o{ completion_target_items : "members"
    users ||--o{ completion_plans : "plans"
    completion_plans ||--o{ completion_plan_items : "ordered subjects"
    users ||--o{ estimates : "optional cache"
    users ||--o{ export_runs : "history (optional)"

    estimates {
        uuid id PK
        uuid user_id FK
        text target_kind
        int min_minutes
        int max_minutes
        text confidence
        text method
        timestamptz calculated_at
        jsonb inputs
    }
```
