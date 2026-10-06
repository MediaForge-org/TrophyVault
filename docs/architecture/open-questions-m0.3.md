# Open questions for M0.3 (Data Model)

M0.2 fixes vocabulary and boundaries only. The following were **deliberately not decided**. M0.2 must not be read as having decided any of them.

## A. Persistence fundamentals
1. Primary-key strategy: bigint / UUID / ULID — per table or uniformly? Public vs. internal identifiers?
2. Soft deletes vs. hard deletes vs. archival, per data class.
3. Versioning and history storage (`GuideVersion`, source revisions, `ProgressSnapshot`): row versioning, history tables, snapshots?
4. PostgreSQL types: enums vs. lookup tables vs. check-constrained text for the *conceptual* lists (relation kinds, group classification, objective types, tracker kinds, lifecycle states). Note: these lists are conceptual and may need to grow.
5. JSONB: which data is normalized and which is JSONB (objective type-specific data, tracker parameters, estimate `inputs`, raw provider records)?
6. Tree storage for objectives: adjacency list + recursive CTE, `ltree`, closure table, materialized path? Ordering of siblings?
7. Indexing and constraint strategy; uniqueness of external identifiers (per provider + kind).
8. Whether Eloquent models live in `app/Models` or inside `app/Domain/*`, and whether domain code uses Eloquent directly.

## B. Catalog and trophies
9. Cardinality of `GameRelease` ↔ `Platform`, `Region`, `Edition` (not assumed 1:1).
10. **Link between Catalog and Trophies** (modelling gap found in M0.2 review): how does a `TrophySet` relate to `Game`/`GameRelease`? Many-to-many? Through releases? A set may serve several platforms/releases, a game may have several sets.
11. Storage of `GameRelation`, `TrophySetRelation`, `TrophyRelation`: one generic relation table per concept, directed or symmetric pairs, kind storage, attributes (e.g. partial auto-pop mapping at trophy level).
12. Where TrophyVault's group classification lives and how it is overridden/curated vs. provider structure.
13. Identity of a `Trophy` across set versions/updates by the provider (stability of the mapping).

## C. Provider / provenance
14. **Where provenance lives** (review finding: the map lists it under Knowledge, but canonical data needs it too, and canonical → knowledge references are otherwise not allowed): a cross-cutting concept outside both classes? Per record, per field?
15. Relationship between `ExternalRecord` (raw), `ExternalIdentifier` (mapping) and normalized rows; retention of raw payloads.
16. Conflict resolution when several providers describe the same entity.
17. Where concrete provider adapters live in the code base (`app/Domain/Sync` vs. a separate `app/Integrations`, …).

## D. Identity and progress
18. **Earned trophies: `User` vs. `PsnAccount`** (review finding): earned data originates from a PSN account. Does `UserTrophy` reference the account, the user, or both? What happens when an account is relinked or shared?
19. Auth/multi-user scope: is the first release effectively single-user with a user table, or fully multi-user?
20. How achievement dimensions are stored: derived (queries/views), cached flags, or snapshots — and the lifecycle-status representation on `UserGame` (see [`completion-semantics.md`](completion-semantics.md)).
21. Which TrophySet(s) count for a user's completion of a multi-set game; treatment of equivalent/auto-pop sets.

## E. Knowledge and personal completion
22. Relationship between `TrackerDefinition` and the objective tree (same structure, reference, or separate).
23. Objective types: how extensible are they at runtime (data-defined types vs. code-known types)?
24. Guide content format and granularity of versioning (guide vs. node).
25. Per-user vs. global definition of "TrophyVault Full Completion".

## F. Planning and export
26. Estimate persistence (stored with `calculated_at` vs. computed on demand) and targets referencing objectives/dimensions.
27. Export profiles: which classes/entities are included, references vs. embedded data, versioned export schema.

## Review findings kept out of scope (not changes to the agreed map)
- The domain map was **not** changed. Items 10, 14, 17 and 18 are the gaps that could justify map adjustments; they are open decisions, not silent edits.
