# 0005 — Guide objectives are recursive and data-driven

Status: accepted · Date: 2026-10-06

## Context
Real completion tasks are trees of arbitrary depth (collectible categories, chapters → missions → requirements, challenge groups).
A trophy with one long guide text or a fixed three-level hierarchy cannot represent or track them.

## Decision
`Objective` nodes form a recursive tree of arbitrary depth; node meaning comes from an extensible *type*. Trackers are defined as data
(`TrackerDefinition`), so new games need guide data, not code. Per-type tables and wide tables with many nullable special columns are
explicitly rejected; M0.3 decides normalized columns vs. JSONB. Guides exist at game/trophy/objective level but are not capped at three levels.

## Consequences
- Needs a tree-storage strategy and ordering (open, M0.3).
- Personal progress lives in separate records referencing objectives ([ADR 0003](0003-canonical-knowledge-personal-data.md)).
- Type-specific behaviour must be data-driven; code-defined types would need a new decision.
- Details: [`knowledge-model.md`](../architecture/knowledge-model.md).
