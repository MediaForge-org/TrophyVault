# 0011 — Catalog ↔ trophy structure: how Game, GameRelease and TrophySet connect

Status: accepted · Date: 2026-10-07 · Resolves M0.3 open questions 9, 10, 11, 12, 13 (builds on [ADR 0004](0004-game-release-trophyset-distinct.md))

## Decision

### 1. Release ↔ platform, region, edition (Q9)
- `game_releases` belong to **one game** (`game_id`, RESTRICT), optionally to **one edition** of that game (composite FK `(edition_id, game_id) → editions(id, game_id)` so an edition of another game is impossible), and have **one region** (`region_code → regions`, default `unknown`; `global` is a real region).
- **Release ↔ platform is n:m** (`game_release_platforms`) because cross-gen/bundle releases exist. A release should have at least one platform; this is application-level (a CHECK cannot span tables).
- `editions` are per game (`UNIQUE (game_id, slug)`), they never create a game.
- `platforms` and `regions` are lookup tables; provider platform names are mapped to our codes in the adapters (a provider value is never trusted as our code).

### 2. Trophy set ↔ game/release (Q10)
- **A trophy set belongs to exactly one game** (`trophy_sets.game_id NOT NULL`, RESTRICT). A collection/compilation with its own trophy list is its own `Game` related by `collection_contains`. This keeps "all lists of a game" a one-hop query and avoids a many-to-many for the 99 % case.
- **A trophy set is linked to the releases that use it via `game_release_trophy_sets` (n:m)** — PS4 and PS5 releases may share or separate sets; one set may serve several releases.
- **Same-game guarantee:** the link table carries `game_id` and has composite FKs to `game_releases(id, game_id)` and `trophy_sets(id, game_id)`. A release can only be linked to a set of its own game — enforced by the database, tested.
- **Trophy set ↔ platform is n:m** (`trophy_set_platforms`); there is no `platform_id` on `trophy_sets`.
- A set exists independently of releases (a set may be known before a release row is).
- The one-game rule for sets is a decision made with the information at hand; if real data shows a set shared by two *games*, the escape hatch is a pure link table plus a primary-game column — documented, not built.

### 3. Relations (Q11)
One table per concept with the same shape: `(id uuid, from_<x>_id, to_<x>_id, kind, created_at, updated_at)`.
- `game_relations`, `trophy_set_relations`, `trophy_relations`. `kind` = CHECK + PHP enum (ADR 0008).
- **Direction:** *directed* kinds keep `from → to` (`remake_of`: from = the remake; `sequel_to`: from = the sequel; `collection_contains`: from = the collection; `autopop`: from = source; `replaces`: from = the newer). *Symmetric* kinds (`related`, `equivalent`, `similar`, `platform_stack`, `regional_stack`, `remaster_stack`) are stored **once as an ordered pair** (`CHECK from < to`).
- **Inverse semantics:** inverses are not stored. `prequel_to` is `sequel_to` read backwards (`Game::sequels()`); `autopop_source`/`autopop_target` are the two ends of one `autopop` row (M0.2 listed two kinds; M0.3 stores one directed relation).
- **Integrity:** no self relations (`CHECK from <> to`); one row per `(kind, unordered pair)` (`UNIQUE (kind, LEAST(from,to), GREATEST(from,to))`) — so `A autopop B` and `B autopop A` cannot coexist; a pair is either `autopop` or `partial_autopop`, never both (partial unique index). Different kinds between the same pair are allowed (`platform_stack` + `equivalent`).
- `LinksPairs::link()` orders symmetric pairs for callers.
- Per-trophy detail of a partial auto-pop is expressed with `trophy_relations(kind='autopop')` between the concrete trophies.
- No attribute columns yet (YAGNI); notes/provenance attach later via `provenance`.

### 4. Group classification (Q12)
`trophy_groups.set_key` / `name` / `ordinal` are the list's structure. **TrophyVault's classification is separate columns**: `classification` (`base|dlc|expansion|free_update|online|challenge|other|unknown`, default `unknown`) and `classification_origin` (`auto|manual`). Imports may set `auto` values; a `manual` value is never overwritten. `unknown` is a real state. Completion rules that need "base" treat unclassified groups as *not yet decidable*, not as base (see [`completion-semantics.md`](../architecture/completion-semantics.md)).

### 5. Trophy identity inside a set (Q13)
A trophy is identified by `(trophy_set_id, set_key)` and a group by `(trophy_set_id, set_key)`. Provider updates (new DLC groups, new trophies) append by key; existing keys are updated in place, so earned rows stay valid. Keys that disappear upstream are **not deleted** (earned rows `RESTRICT` it); the importer records an `import_errors` warning. If real data shows removed trophies are common, a `retired_at` column is added then.
Provider rarity/earned-rate is **not** a column on `trophies` (it varies by provider and over time): logical table `trophy_stats(trophy_id, provider_code, earned_rate, observed_at)`, M2.
Look-alike trophies of different sets are distinct rows (tested), linked by `trophy_relations`.

### 6. Platinum and groups
`CREATE UNIQUE INDEX … ON trophies (trophy_set_id) WHERE grade = 'platinum'` — at most one platinum per set; zero is valid (`not_applicable` is derived, never stored).

## Consequences
- "All trophy lists of a game" and "all releases of a list" are single-hop queries.
- Composite FKs need extra unique constraints on parents `(id, game_id)`, `(id, trophy_set_id)`: small, deliberate redundancy; documented in the data dictionary.
- Relation tables look similar but are separate tables with real FKs (no polymorphism).
