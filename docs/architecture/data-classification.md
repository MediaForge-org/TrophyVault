# Data ownership and classification

([ADR 0003](../decisions/0003-canonical-knowledge-personal-data.md) — **Decided**)

Every piece of data belongs to exactly one class. The class decides who may own it, who may reference it, and how it is exported or deleted.

| Class | What | Examples | Owned by | May reference |
| --- | --- | --- | --- | --- |
| **Canonical** | Objective facts about games and trophy structure | Game, GameRelease, Edition, Platform, Region, TrophySet, TrophyGroup, Trophy, relations | nobody (shared) | other canonical data, provider identities (as external ids). Provenance **points at** canonical rows from outside ([ADR 0010](../decisions/0010-provenance-and-sync-model.md)); canonical rows hold no provenance reference |
| **Knowledge** | How something is completed | Guide, GuideVersion, Objective, ObjectiveRelation, TrackerDefinition | nobody (shared) | canonical data, sources (leaf table, see [ADR 0010](../decisions/0010-provenance-and-sync-model.md)) — **never personal data** |
| **Personal** | One user's progress and opinions | UserGame, account trophy sets/trophies (owned via a PsnAccount of the user, [ADR 0012](../decisions/0012-account-owned-progress.md)), ProgressSnapshot, UserObjectiveProgress, UserTrackerProgress, notes, personal estimates | a `User` | canonical and knowledge data, by TrophyVault identity |
| **Provider/operational** | Traces of external communication | Provider, ImportRun (incl. former SyncRun), ExternalRecord, SyncError, Source, Provenance | the system | whatever it produced (for provenance); provenance/source rows are operational data pointing at shared data |

## Rules  (Decided)

1. **No mixing.** A record never carries both shared and personal facts. Example: Objective *"Study American Alligator"* (knowledge) vs.
   `UserObjectiveProgress` "studied: yes" (personal). The same applies to Trophy (canonical) vs. an account trophy (earned at …).
2. **Direction of references.** Personal → shared, never shared → personal. Consequence: the same guide/tracker definition serves many users, and
   deleting a user cannot corrupt shared data.
3. **Estimates are derived personal data** (they depend on a user's progress), never a column on `Game`.
4. **Provider data is input, not model.** Raw provider records may be kept for traceability; domain tables hold normalized data
   ([`provider-boundary.md`](provider-boundary.md)).
5. **Third-party content is separable.** Imported copyrighted content carries provenance so it can be identified and removed independently of code and personal data.
6. **Export** is per class: a full TrophyVault export (JSON) contains all classes with their provenance, so the state can be handed to other software.
   Personal data is exported with the user; shared data may be exported by reference or in full. Export is allow-list based; secrets and operational tables are excluded by construction ([`../data-model/security-and-performance.md`](../data-model/security-and-performance.md)).

## Consequences for M0.3 (**implemented**)

Personal data lives in separate tables that reference shared data by foreign key in the allowed direction; there are no shared tables with a nullable `user_id`. Deletion and history per class:
see [`../data-model/conventions.md`](../data-model/conventions.md) and [ADR 0008](../decisions/0008-storage-conventions.md). Secret data is a fifth class handled outside domain tables ([`../data-model/security-and-performance.md`](../data-model/security-and-performance.md)).
