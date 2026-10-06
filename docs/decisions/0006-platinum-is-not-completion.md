# 0006 — Platinum is not equivalent to completion

Status: accepted · Date: 2026-10-06

## Context
The Platinum trophy often pops before DLC groups, collectibles or in-game completion are done. Players treat those as part of "finished".
Some games have no Platinum at all.

## Decision
Completion is multi-dimensional: Platinum Status (incl. `not_applicable`), Trophy Set Completion, Base Trophy Completion, All Trophy Groups
Completion, Ingame Completion, Custom Completion and TrophyVault Full Completion. The dimensions are independent facts. A single status enum must not
mix them; user *intent* (not started, playing, paused, backlog, abandoned) is separated from achievement *dimensions* (platinum, trophy 100 %, ingame 100 %, full).

## Consequences
- `UserGame` cannot be a single "status" column; M0.3 decides how dimensions are stored (derived vs. stored).
- Games without Platinum are first-class.
- Calculation logic and the definition of Full Completion (global/per game/per user) come later.
- Details: [`completion-semantics.md`](../architecture/completion-semantics.md).
