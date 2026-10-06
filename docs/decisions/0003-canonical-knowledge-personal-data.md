# 0003 — Canonical, Knowledge and Personal data are separate

Status: accepted · Date: 2026-10-06

## Context
Trophy structure, guide knowledge and a user's progress are easy to blur (e.g. "objective done" flag on the objective). That prevents sharing guides between
users, makes export/deletion unclear and mixes copyrighted content with personal data.

## Decision
Three data classes — **Canonical** (games, trophy structure), **Knowledge** (guides, objectives, trackers, sources) and **Personal**
(progress, notes, estimates) — plus operational provider/sync data. Records belong to exactly one class. References point from personal to shared data, never the reverse.
Example: Objective "Study American Alligator" is knowledge; `UserObjectiveProgress` is personal.

## Consequences
- Guides/trackers are reusable across users; deleting a user cannot damage shared data.
- Personal data is modelled in separate tables that reference shared data; no shared tables with nullable `user_id` (implemented in M0.3, see [ADR 0012](0012-account-owned-progress.md)).
- Some queries need joins across classes; accepted.
- Details: [`data-classification.md`](../architecture/data-classification.md).
