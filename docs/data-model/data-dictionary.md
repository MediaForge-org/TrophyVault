# Data dictionary (physical schema)

The tables below exist in the database after M0.3. They are listed with the columns, keys, constraints and indexes **as migrated** (generated from the PostgreSQL catalog; `DocumentationConsistencyTest` fails if a table or column is missing here).
Tables that are part of the v1 logical model but not yet migrated are described in [`logical-model.md`](logical-model.md); their rules are in [ADR 0010](../decisions/0010-provenance-and-sync-model.md) and [ADR 0013](../decisions/0013-objectives-trackers-guides.md).

Common to all tables: `id uuid` primary key with default `uuidv7()` (exceptions noted), `created_at`/`updated_at timestamptz` where the table owns rows. See [`conventions.md`](conventions.md).

Delete policy abbreviations: **CASCADE** child follows parent, **RESTRICT** parent cannot be deleted while children exist.

### `providers`

**Domain:** Sync / Providers (reference) · **Purpose:** An information source (PSN, PSNProfiles, PowerPyx, PlayStationTrophies, community, manual). Says nothing about whether a connector exists.

**Lifecycle / deletion:** Reference data seeded by its migration; changes via new migrations.

**Notes:** Identity of provenance and external ids.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `code` | text | no |  |
| `name` | text | no |  |
| `kind` | text | no |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (code)
- **Check** `providers_code_check`: `((code ~ '^[a-z][a-z0-9_]*$'))`
- **Check** `providers_kind_check`: `((kind = ANY (ARRAY['platform', 'third_party', 'community', 'manual'])))`
- **Check** `providers_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`

### `platforms`

**Domain:** Catalog (reference) · **Purpose:** Our canonical platform vocabulary (`ps3`, `ps4`, `ps5`, `vita`, `psp`, `pc`). Provider platform names are mapped to these codes in adapters.

**Lifecycle / deletion:** Reference data seeded by its migration.

**Notes:** `pc` exists for PlayStation PC trophy lists.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `code` | text | no |  |
| `name` | text | no |  |
| `sort_order` | smallint | no | `'0'::smallint` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (code)
- **Check** `platforms_code_check`: `((code ~ '^[a-z][a-z0-9_]*$'))`
- **Check** `platforms_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`

### `regions`

**Domain:** Catalog (reference) · **Purpose:** Release regions incl. the real values `global` and `unknown`.

**Lifecycle / deletion:** Reference data seeded by its migration.

**Notes:** Never NULL: a release always has a region.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `code` | text | no |  |
| `name` | text | no |  |
| `sort_order` | smallint | no | `'0'::smallint` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (code)
- **Check** `regions_code_check`: `((code ~ '^[a-z][a-z0-9_]*$'))`
- **Check** `regions_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`

### `users`

**Domain:** Identity · **Purpose:** Local TrophyVault user (owner of personal data and PSN accounts). Authentication details are a later decision; the table is the multi-user anchor.

**Lifecycle / deletion:** Hard delete; cascades to the user's PSN accounts and their progress.

**Notes:** Email unique case-insensitively (`lower(email)`).

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `name` | text | no |  |
| `email` | text | no |  |
| `password` | text | no |  |
| `remember_token` | character varying(100) | yes |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **Check** `users_email_check`: `((email ~ '^\S+$'))`
- **Unique index** `users_email_lower_unique`: `USING btree (lower(email))`

### `psn_accounts`

**Domain:** Identity · **Purpose:** A linked PlayStation account, separate from the local user. Earned-trophy state hangs on it ([ADR 0012](../decisions/0012-account-owned-progress.md)).

**Lifecycle / deletion:** Hard delete (cascade from user); cascades to its progress rows. Credentials are NOT stored here (planned `psn_account_credentials`).

**Notes:** `psn_account_id` is PSN's account id (opaque text), unique across all users. `profile` = non-secret profile data only.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `user_id` | uuid | no |  |
| `psn_account_id` | text | no |  |
| `online_id` | text | no |  |
| `status` | text | no | `'active'` |
| `last_synced_at` | timestamp(0) with time zone | yes |  |
| `profile` | jsonb | no | `'{}'` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (user_id) → users(id) ON DELETE CASCADE
- **Unique:** (psn_account_id)
- **Check** `psn_accounts_online_id_check`: `((online_id ~ '^\S+$'))`
- **Check** `psn_accounts_profile_check`: `(jsonb_is_safe_object(profile))`
- **Check** `psn_accounts_psn_account_id_check`: `((psn_account_id ~ '^\S+$'))`
- **Check** `psn_accounts_status_check`: `((status = ANY (ARRAY['active', 'disabled'])))`
- **Index** `psn_accounts_user_id_index`: `USING btree (user_id)`

### `games`

**Domain:** Catalog · **Purpose:** The work, independent of platform releases. Root of the catalog.

**Lifecycle / deletion:** Retired via `retired_at`, never deleted while releases/sets exist (RESTRICT).

**Notes:** `slug` = stable human handle; `title`/`sort_title` for display/sorting; trigram index for substring search.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `slug` | text | no |  |
| `title` | text | no |  |
| `sort_title` | text | no |  |
| `retired_at` | timestamp(0) with time zone | yes |  |
| `metadata` | jsonb | no | `'{}'` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **Unique:** (slug)
- **Check** `games_metadata_check`: `(jsonb_is_safe_object(metadata))`
- **Check** `games_slug_check`: `((slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'))`
- **Check** `games_title_check`: `(((title ~ '^\S+(\s+\S+)*$') AND (sort_title ~ '^\S+(\s+\S+)*$')))`
- **Index** `games_sort_title_index`: `USING btree (sort_title)`
- **Index** `games_title_trgm_idx`: `USING gin (lower(title) gin_trgm_ops)`

### `editions`

**Domain:** Catalog · **Purpose:** A marketing edition of one game (Standard, Deluxe, …). Never creates a separate game.

**Lifecycle / deletion:** Follows its game; RESTRICT while releases reference it.

**Notes:** `UNIQUE (id, game_id)` exists only as the target of the composite FK from `game_releases`.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `game_id` | uuid | no |  |
| `slug` | text | no |  |
| `name` | text | no |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (game_id) → games(id) ON DELETE RESTRICT
- **Unique:** (game_id, slug)
- **Unique:** (id, game_id)
- **Check** `editions_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`
- **Check** `editions_slug_check`: `((slug ~ '^[a-z0-9]+(-[a-z0-9]+)*$'))`

### `game_releases`

**Domain:** Catalog · **Purpose:** A concrete published variant of a game: optional edition, one region, n platforms, optional release date.

**Lifecycle / deletion:** Retired via `retired_at`; FK RESTRICT to its game.

**Notes:** Composite FK `(edition_id, game_id)` keeps the edition inside the same game. Platforms via `game_release_platforms`.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `game_id` | uuid | no |  |
| `edition_id` | uuid | yes |  |
| `region_code` | text | no | `'unknown'` |
| `title` | text | yes |  |
| `release_date` | date | yes |  |
| `retired_at` | timestamp(0) with time zone | yes |  |
| `metadata` | jsonb | no | `'{}'` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (edition_id, game_id) → editions(id, game_id) ON DELETE RESTRICT
- **FK:** (game_id) → games(id) ON DELETE RESTRICT
- **FK:** (region_code) → regions(code) ON DELETE RESTRICT
- **Unique:** (id, game_id)
- **Check** `game_releases_metadata_check`: `(jsonb_is_safe_object(metadata))`
- **Check** `game_releases_title_check`: `(((title IS NULL) OR (title ~ '^\S+(\s+\S+)*$')))`
- **Index** `game_releases_edition_id_index`: `USING btree (edition_id)`
- **Index** `game_releases_game_id_index`: `USING btree (game_id)`
- **Index** `game_releases_region_code_index`: `USING btree (region_code)`

### `game_release_platforms`

**Domain:** Catalog · **Purpose:** Platforms a release is published on (n:m, cross-gen releases).

**Lifecycle / deletion:** Cascades with the release.

**Notes:** At least one platform per release is an application rule.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `game_release_id` | uuid | no |  |
| `platform_code` | text | no |  |

- **Primary key:** (game_release_id, platform_code)
- **FK:** (game_release_id) → game_releases(id) ON DELETE CASCADE
- **FK:** (platform_code) → platforms(code) ON DELETE RESTRICT
- **Index** `game_release_platforms_platform_code_index`: `USING btree (platform_code)`

### `game_relations`

**Domain:** Catalog · **Purpose:** Relation between two games: `remake_of`, `remaster_of`, `sequel_to`, `collection_contains` (directed), `related` (symmetric, ordered pair).

**Lifecycle / deletion:** Cascades with either game.

**Notes:** Unique per `(kind, unordered pair)`; no self relation. `prequel_to` is not stored.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `from_game_id` | uuid | no |  |
| `to_game_id` | uuid | no |  |
| `kind` | text | no |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (from_game_id) → games(id) ON DELETE CASCADE
- **FK:** (to_game_id) → games(id) ON DELETE CASCADE
- **Check** `game_relations_kind_check`: `((kind = ANY (ARRAY['remake_of', 'remaster_of', 'sequel_to', 'collection_contains', 'related'])))`
- **Check** `game_relations_no_self_check`: `((from_game_id <> to_game_id))`
- **Check** `game_relations_symmetric_order_check`: `(((kind <> 'related') OR (from_game_id < to_game_id)))`
- **Index** `game_relations_from_game_id_index`: `USING btree (from_game_id)`
- **Index** `game_relations_to_game_id_index`: `USING btree (to_game_id)`
- **Unique index** `game_relations_pair_unique`: `USING btree (kind, LEAST(from_game_id, to_game_id), GREATEST(from_game_id, to_game_id))`

### `trophy_sets`

**Domain:** Trophies · **Purpose:** An independent PlayStation trophy list; belongs to exactly one game; independent of releases and platforms.

**Lifecycle / deletion:** Retired via `retired_at`; FK RESTRICT to game; RESTRICT while accounts hold it.

**Notes:** Provider ids (`npCommunicationId`) live in `external_identifiers`. `UNIQUE (id, game_id)` is the target of `game_release_trophy_sets`.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `game_id` | uuid | no |  |
| `name` | text | no |  |
| `retired_at` | timestamp(0) with time zone | yes |  |
| `metadata` | jsonb | no | `'{}'` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (game_id) → games(id) ON DELETE RESTRICT
- **Unique:** (id, game_id)
- **Check** `trophy_sets_metadata_check`: `(jsonb_is_safe_object(metadata))`
- **Check** `trophy_sets_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`
- **Index** `trophy_sets_game_id_index`: `USING btree (game_id)`

### `trophy_set_platforms`

**Domain:** Trophies · **Purpose:** Platform labels of a trophy list (n:m).

**Lifecycle / deletion:** Cascades with the set.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `trophy_set_id` | uuid | no |  |
| `platform_code` | text | no |  |

- **Primary key:** (trophy_set_id, platform_code)
- **FK:** (platform_code) → platforms(code) ON DELETE RESTRICT
- **FK:** (trophy_set_id) → trophy_sets(id) ON DELETE CASCADE
- **Index** `trophy_set_platforms_platform_code_index`: `USING btree (platform_code)`

### `game_release_trophy_sets`

**Domain:** Trophies ↔ Catalog · **Purpose:** Which releases use which trophy list (n:m). Carries `game_id` so release and set are forced to belong to the same game.

**Lifecycle / deletion:** Cascades with release or set.

**Notes:** Composite FKs to `game_releases(id, game_id)` and `trophy_sets(id, game_id)`.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `game_release_id` | uuid | no |  |
| `trophy_set_id` | uuid | no |  |
| `game_id` | uuid | no |  |

- **Primary key:** (game_release_id, trophy_set_id)
- **FK:** (game_release_id, game_id) → game_releases(id, game_id) ON DELETE CASCADE
- **FK:** (trophy_set_id, game_id) → trophy_sets(id, game_id) ON DELETE CASCADE
- **Index** `game_release_trophy_sets_trophy_set_id_index`: `USING btree (trophy_set_id)`

### `external_identifiers`

**Domain:** Catalog (cross-cutting) · **Purpose:** Maps provider ids (provider, namespace, value) to a game, release or trophy set (exclusive arc with real FKs).

**Lifecycle / deletion:** Retired via `retired_at`; cascades with the entity.

**Notes:** Exactly one entity column is set. Active `(provider, namespace, value)` is unique ([ADR 0009](../decisions/0009-external-identifiers.md)).

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `provider_code` | text | no |  |
| `namespace` | text | no |  |
| `value` | text | no |  |
| `game_id` | uuid | yes |  |
| `game_release_id` | uuid | yes |  |
| `trophy_set_id` | uuid | yes |  |
| `retired_at` | timestamp(0) with time zone | yes |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (game_id) → games(id) ON DELETE CASCADE
- **FK:** (game_release_id) → game_releases(id) ON DELETE CASCADE
- **FK:** (provider_code) → providers(code) ON DELETE RESTRICT
- **FK:** (trophy_set_id) → trophy_sets(id) ON DELETE CASCADE
- **Check** `external_identifiers_namespace_check`: `((namespace ~ '^[a-z][a-z0-9_]*$'))`
- **Check** `external_identifiers_one_entity_check`: `((num_nonnulls(game_id, game_release_id, trophy_set_id) = 1))`
- **Check** `external_identifiers_value_check`: `((value ~ '^\S+$'))`
- **Index** `external_identifiers_game_idx`: `USING btree (game_id) WHERE (game_id IS NOT NULL)`
- **Index** `external_identifiers_game_release_idx`: `USING btree (game_release_id) WHERE (game_release_id IS NOT NULL)`
- **Index** `external_identifiers_trophy_set_idx`: `USING btree (trophy_set_id) WHERE (trophy_set_id IS NOT NULL)`
- **Unique index** `external_identifiers_active_unique`: `USING btree (provider_code, namespace, value) WHERE (retired_at IS NULL)`

### `trophy_groups`

**Domain:** Trophies · **Purpose:** Structural group of a trophy list (`default`, `001`, …) plus TrophyVault's own classification.

**Lifecycle / deletion:** Cascades with the set; deletion of the set is blocked while accounts hold it.

**Notes:** `set_key` = key inside the list. `classification`/`classification_origin` are TrophyVault-owned and independent of structure; `unknown` is a real value.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `trophy_set_id` | uuid | no |  |
| `set_key` | text | no |  |
| `name` | text | no |  |
| `description` | text | yes |  |
| `ordinal` | integer | no |  |
| `classification` | text | no | `'unknown'` |
| `classification_origin` | text | no | `'auto'` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (trophy_set_id) → trophy_sets(id) ON DELETE CASCADE
- **Unique:** (id, trophy_set_id)
- **Unique:** (trophy_set_id, set_key)
- **Check** `trophy_groups_classification_check`: `((classification = ANY (ARRAY['base', 'dlc', 'expansion', 'free_update', 'online', 'challenge', 'other', 'unknown'])))`
- **Check** `trophy_groups_classification_origin_check`: `((classification_origin = ANY (ARRAY['auto', 'manual'])))`
- **Check** `trophy_groups_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`
- **Check** `trophy_groups_ordinal_check`: `((ordinal >= 0))`
- **Check** `trophy_groups_set_key_check`: `((set_key ~ '^\S+$'))`
- **Index** `trophy_groups_trophy_set_id_ordinal_index`: `USING btree (trophy_set_id, ordinal)`

### `trophies`

**Domain:** Trophies · **Purpose:** One trophy definition inside its list and group. Look-alike trophies of different sets are separate rows.

**Lifecycle / deletion:** Cascades with the set/group; RESTRICT while any account has earned it.

**Notes:** `UNIQUE (trophy_set_id, set_key)`; at most one platinum per set (partial unique index). `UNIQUE (id, trophy_set_id)` is the target of `psn_account_trophies`.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `trophy_set_id` | uuid | no |  |
| `trophy_group_id` | uuid | no |  |
| `set_key` | integer | no |  |
| `grade` | text | no |  |
| `name` | text | no |  |
| `description` | text | yes |  |
| `is_hidden` | boolean | no | `false` |
| `ordinal` | integer | no |  |
| `metadata` | jsonb | no | `'{}'` |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (trophy_group_id, trophy_set_id) → trophy_groups(id, trophy_set_id) ON DELETE CASCADE
- **FK:** (trophy_set_id) → trophy_sets(id) ON DELETE CASCADE
- **Unique:** (id, trophy_set_id)
- **Unique:** (trophy_set_id, set_key)
- **Check** `trophies_grade_check`: `((grade = ANY (ARRAY['bronze', 'silver', 'gold', 'platinum'])))`
- **Check** `trophies_metadata_check`: `(jsonb_is_safe_object(metadata))`
- **Check** `trophies_name_check`: `((name ~ '^\S+(\s+\S+)*$'))`
- **Check** `trophies_ordinal_check`: `((ordinal >= 0))`
- **Check** `trophies_set_key_check`: `((set_key >= 0))`
- **Index** `trophies_trophy_group_id_ordinal_index`: `USING btree (trophy_group_id, ordinal)`
- **Index** `trophies_trophy_set_id_ordinal_index`: `USING btree (trophy_set_id, ordinal)`
- **Unique index** `trophies_one_platinum_per_set`: `USING btree (trophy_set_id) WHERE (grade = 'platinum')`

### `trophy_set_relations`

**Domain:** Trophies · **Purpose:** Relation between two trophy sets: stacks/equivalence (symmetric) and `autopop`/`partial_autopop` (directed).

**Lifecycle / deletion:** Cascades with either set.

**Notes:** No self relation; one row per `(kind, unordered pair)`; a pair is either `autopop` or `partial_autopop`.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `from_trophy_set_id` | uuid | no |  |
| `to_trophy_set_id` | uuid | no |  |
| `kind` | text | no |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (from_trophy_set_id) → trophy_sets(id) ON DELETE CASCADE
- **FK:** (to_trophy_set_id) → trophy_sets(id) ON DELETE CASCADE
- **Check** `trophy_set_relations_kind_check`: `((kind = ANY (ARRAY['platform_stack', 'regional_stack', 'remaster_stack', 'equivalent', 'related', 'autopop', 'partial_autopop'])))`
- **Check** `trophy_set_relations_no_self_check`: `((from_trophy_set_id <> to_trophy_set_id))`
- **Check** `trophy_set_relations_symmetric_order_check`: `(((kind <> ALL (ARRAY['platform_stack', 'regional_stack', 'remaster_stack', 'equivalent', 'related'])) OR (from_trophy_set_id < to_trophy_set_id)))`
- **Index** `trophy_set_relations_from_trophy_set_id_index`: `USING btree (from_trophy_set_id)`
- **Index** `trophy_set_relations_to_trophy_set_id_index`: `USING btree (to_trophy_set_id)`
- **Unique index** `trophy_set_relations_autopop_exclusive`: `USING btree (LEAST(from_trophy_set_id, to_trophy_set_id), GREATEST(from_trophy_set_id, to_trophy_set_id)) WHERE (kind = ANY (ARRAY['autopop', 'partial_autopop']))`
- **Unique index** `trophy_set_relations_pair_unique`: `USING btree (kind, LEAST(from_trophy_set_id, to_trophy_set_id), GREATEST(from_trophy_set_id, to_trophy_set_id))`

### `trophy_relations`

**Domain:** Trophies · **Purpose:** Relation between two trophies: `equivalent`/`similar`/`related` (symmetric), `replaces`/`autopop` (directed).

**Lifecycle / deletion:** Cascades with either trophy.

**Notes:** Same pair rules as set relations.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `id` | uuid | no | `uuidv7()` |
| `from_trophy_id` | uuid | no |  |
| `to_trophy_id` | uuid | no |  |
| `kind` | text | no |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (id)
- **FK:** (from_trophy_id) → trophies(id) ON DELETE CASCADE
- **FK:** (to_trophy_id) → trophies(id) ON DELETE CASCADE
- **Check** `trophy_relations_kind_check`: `((kind = ANY (ARRAY['equivalent', 'similar', 'related', 'replaces', 'autopop'])))`
- **Check** `trophy_relations_no_self_check`: `((from_trophy_id <> to_trophy_id))`
- **Check** `trophy_relations_symmetric_order_check`: `(((kind <> ALL (ARRAY['equivalent', 'similar', 'related'])) OR (from_trophy_id < to_trophy_id)))`
- **Index** `trophy_relations_from_trophy_id_index`: `USING btree (from_trophy_id)`
- **Index** `trophy_relations_to_trophy_id_index`: `USING btree (to_trophy_id)`
- **Unique index** `trophy_relations_pair_unique`: `USING btree (kind, LEAST(from_trophy_id, to_trophy_id), GREATEST(from_trophy_id, to_trophy_id))`

### `psn_account_trophy_sets`

**Domain:** Player progress · **Purpose:** "This PSN account has this trophy list" (library membership + sync metadata).

**Lifecycle / deletion:** Hard delete; cascades from the account; RESTRICT on the set.

**Notes:** No completion columns — completion is derived.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `psn_account_id` | uuid | no |  |
| `trophy_set_id` | uuid | no |  |
| `first_seen_at` | timestamp(0) with time zone | no |  |
| `last_synced_at` | timestamp(0) with time zone | yes |  |
| `provider_reported_progress` | smallint | yes |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (psn_account_id, trophy_set_id)
- **FK:** (psn_account_id) → psn_accounts(id) ON DELETE CASCADE
- **FK:** (trophy_set_id) → trophy_sets(id) ON DELETE RESTRICT
- **Check** `psn_account_trophy_sets_progress_check`: `(((provider_reported_progress IS NULL) OR ((provider_reported_progress >= 0) AND (provider_reported_progress <= 100))))`
- **Index** `psn_account_trophy_sets_trophy_set_id_index`: `USING btree (trophy_set_id)`

### `psn_account_trophies`

**Domain:** Player progress · **Purpose:** One row per **earned** trophy of a PSN account (absence = not earned).

**Lifecycle / deletion:** Hard delete; cascades from the account (through its set membership); RESTRICT on the trophy definition.

**Notes:** `earned_at` NULL = earned, time unknown. Composite FKs guarantee trophy ∈ set ∈ account library.

| Column | Type | Null | Default |
| --- | --- | --- | --- |
| `psn_account_id` | uuid | no |  |
| `trophy_id` | uuid | no |  |
| `trophy_set_id` | uuid | no |  |
| `earned_at` | timestamp(0) with time zone | yes |  |
| `created_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |
| `updated_at` | timestamp(0) with time zone | no | `CURRENT_TIMESTAMP` |

- **Primary key:** (psn_account_id, trophy_id)
- **FK:** (psn_account_id, trophy_set_id) → psn_account_trophy_sets(psn_account_id, trophy_set_id) ON DELETE CASCADE
- **FK:** (trophy_id, trophy_set_id) → trophies(id, trophy_set_id) ON DELETE RESTRICT
- **Index** `psn_account_trophies_psn_account_id_earned_at_index`: `USING btree (psn_account_id, earned_at)`
- **Index** `psn_account_trophies_psn_account_id_trophy_set_id_index`: `USING btree (psn_account_id, trophy_set_id)`
- **Index** `psn_account_trophies_trophy_id_index`: `USING btree (trophy_id)`

