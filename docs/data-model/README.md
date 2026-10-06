# Data model (M0.3)

The PostgreSQL 18 data-architecture baseline for TrophyVault v1. It turns the domain map of M0.2 ([`../architecture/`](../architecture/README.md)) into concrete tables.

## Two views, deliberately

| View | Document | Content |
| --- | --- | --- |
| **Logical v1 model** (everything planned for v1) | [`logical-model.md`](logical-model.md), [`er-diagrams.md`](er-diagrams.md) | all ~44 tables, relations, ownership, where each M0.2 concept went (incl. concepts that are *not* tables) |
| **Physical schema** (what is migrated *now*) | [`data-dictionary.md`](data-dictionary.md), [`migration-roadmap.md`](migration-roadmap.md) | the 20 tables created in M0.3 (20 migration files) and the order/grouping of all later migrations |

**Why not migrate everything now?** Tables whose first user is a later milestone (guides, trackers, planning, sync-run history) would sit empty and could not be tested against real behaviour; their design is fixed here (ADRs 0010, 0013), their migrations arrive with the feature that needs them. The tables implemented now are the ones that carry the platform's structure and that every later milestone builds on: reference data, catalog, trophy structure, external identifiers, and the identity/account progress that anchors personal data.

## Reading order

1. [`conventions.md`](conventions.md) — ids, time, enums, deletion, JSONB, FK patterns, naming, code placement
2. [`logical-model.md`](logical-model.md) + [`er-diagrams.md`](er-diagrams.md)
3. [`data-dictionary.md`](data-dictionary.md) (physical tables)
4. [`indexes-and-constraints.md`](indexes-and-constraints.md) — query patterns, indexes, uniqueness, CHECKs, delete policy
5. [`scenarios.md`](scenarios.md) — acceptance scenarios A–H and which tests prove them
6. [`migration-roadmap.md`](migration-roadmap.md)
7. [`security-and-performance.md`](security-and-performance.md)
8. [`database-safety.md`](database-safety.md) — how tests and destructive commands are kept away from real data
9. [`open-questions-resolution.md`](open-questions-resolution.md) — all 27 M0.2 questions

## Decisions (ADRs)

| ADR | Decision |
| --- | --- |
| [0007](../decisions/0007-primary-keys-uuidv7.md) | UUIDv7 primary keys |
| [0008](../decisions/0008-storage-conventions.md) | timestamptz/UTC, enum policy, deletion/retirement, JSONB policy, secrets |
| [0009](../decisions/0009-external-identifiers.md) | external identifiers (exclusive arc) and providers |
| [0010](../decisions/0010-provenance-and-sync-model.md) | cross-cutting provenance, one run model, retention |
| [0011](../decisions/0011-catalog-and-trophy-structure.md) | Game ↔ GameRelease ↔ TrophySet, relation storage, group classification |
| [0012](../decisions/0012-account-owned-progress.md) | progress belongs to the PSN account; identity; multi-user; derived completion |
| [0013](../decisions/0013-objectives-trackers-guides.md) | objective tree, trackers, guide versions, estimates |
| [0014](../decisions/0014-database-safety.md) | layered database safety (restricted test role, guarded destructive commands) |

## Maturity labels (as in M0.2)

**Decided** = implemented or fixed by ADR. **Planned** = logical design fixed, table not yet migrated. **Deferred** = consciously postponed with a named blocker (only conflict resolution between providers, see the resolution matrix).
