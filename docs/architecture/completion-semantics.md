# Completion semantics

([ADR 0006](../decisions/0006-platinum-is-not-completion.md) — **Decided**)

> Platinum is never a synonym for game completion.

## Dimensions  (Decided that they are separate; computation is later)

| Dimension | Source of truth | Meaning |
| --- | --- | --- |
| **Platinum Status** | trophy data | Platinum earned / not earned / **`not_applicable`** (the set has no Platinum) |
| **Trophy Set Completion** | trophy data | every trophy of a TrophySet earned |
| **Base Trophy Completion** | trophy data + group classification | every trophy in the base group(s) |
| **All Trophy Groups Completion** | trophy data | every group of the set (base + DLC/other) |
| **Ingame Completion** | knowledge (trackers/objectives) + personal progress | the game's own 100 % notion |
| **Custom Completion** | user-defined | a user-chosen set of targets |
| **TrophyVault Full Completion** | combination | the configured combination of the above for a game |

Games without a Platinum are fully valid games (`not_applicable`, not "missing").

Base Trophy Completion depends on the group classification, which may be `unknown`; how completion is reported for unclassified groups is **Open** (question 12).

## Independence  (Decided)

The dimensions are independent facts. A game can be *platinumed* but not *trophy 100 %* (DLC groups open) and not *ingame 100 %*.
Therefore:

- **No single status enum** may carry these dimensions. The earlier idea of one list
  (`not_started, playing, paused, backlog, platinumed, trophy_100, full_completion, abandoned`) mixes two different things:
  - **Intent/lifecycle** (not_started, playing, paused, backlog, abandoned) — a *personal* choice about what the user is doing with the game;
  - **Achievement dimensions** (platinumed, trophy_100, ingame_100, full_completion) — *facts* that are (mostly) derived from data.
- Conceptually `UserGame` holds the lifecycle intent; achievement dimensions are derived or stored as separate facts. The exact storage
  (derived views vs. stored flags vs. snapshots) is **open** (M0.3).

## Illustration

```text
Game X (one TrophySet: base group + 2 DLC groups, has Platinum)
  Platinum Status ............ earned
  Base Trophy Completion ..... complete
  All Trophy Groups .......... 1 of 3 groups complete   → Trophy Set 100 %: no
  Ingame Completion .......... 58 / 70 tracked           → no
  TrophyVault Full Completion  not reached (depends on its configured definition)
  Lifecycle (UserGame) ....... playing
```

## Where each dimension is computed from

- Trophy dimensions: Player Progress (`UserTrophy`) against Trophies structure (+ group classification).
- Ingame: Personal Completion (`UserObjectiveProgress`, `UserTrackerProgress`) against Knowledge (objectives, trackers).
- Full Completion: a rule over the above; whether the rule is global, per game or per user is **open**.
- Calculation logic itself is **not implemented in M0.2**.

## Multi-set games

A Game may have several TrophySets (platform/regional/remaster stacks). Which set(s) count for a user's completion, and how
equivalent or auto-pop sets are treated, is **open** — see [`open-questions-m0.3.md`](open-questions-m0.3.md).

## Planning relation  (Conceptual)

Estimates target one of these dimensions (Platinum, Trophy 100 %, Ingame 100 %, Objective, Full Completion) and are separate
objects with `target`, `estimate_min/max`, `confidence`, `method`, `calculated_at`, `inputs`. Not implemented in M0.2.
