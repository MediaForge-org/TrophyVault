# 0004 — Game, GameRelease and TrophySet are distinct concepts

Status: accepted · Date: 2026-10-06

## Context
A "game" in everyday speech covers the work, its platform releases and its trophy list. PS3/PS4/PS5 versions, remasters and regional versions
have different releases and often different trophy lists, sometimes shared ones.

## Decision
- `Game` = the work. `GameRelease` = a concrete published variant (platform, region, edition, …). `Edition` is a release concept, not a Game.
- `TrophySet` = an independent trophy list, identified externally by e.g. `npCommunicationId`. It is **not** a Game.
- A Game may have several TrophySets; a TrophySet may relate to several platforms/releases. **No 1:1 relationship is assumed anywhere.**
- `TrophyGroup` is a provider structure and is **not** automatically DLC; TrophyVault's semantic classification is separate.
- `Trophy` belongs to its set/group context; look-alike trophies of different sets are separate records, linked via `TrophyRelation` (`equivalent`, …).
- Relations (`GameRelation`, `TrophySetRelation`, `TrophyRelation`) are first-class; auto-pop is a relation, not a boolean on Game.

## Consequences
- Stacks, remasters and auto-pop can be expressed without hacks.
- The Catalog↔Trophies link and cardinalities were left open here and **decided in M0.3** ([ADR 0011](0011-catalog-and-trophy-structure.md)).
- Relation kind lists are conceptual, not a fixed enum.
