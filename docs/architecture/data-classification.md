# Data ownership and classification

([ADR 0003](../decisions/0003-canonical-knowledge-personal-data.md) — **Decided**)

Every piece of data belongs to exactly one class. The class decides who may own it, who may reference it, and how it is exported or deleted.

| Class | What | Examples | Owned by | May reference |
| --- | --- | --- | --- | --- |
| **Canonical** | Objective facts about games and trophy structure | Game, GameRelease, Edition, Platform, Region, TrophySet, TrophyGroup, Trophy, relations | nobody (shared) | other canonical data, provider identities (as external ids); provenance is **Open** (question 14) — `Source` is listed under Knowledge, so a canonical → Source reference needs an explicit decision |
| **Knowledge** | How something is completed | Guide, GuideVersion, Objective, ObjectiveRelation, TrackerDefinition, Source | nobody (shared) | canonical data, sources — **never personal data** |
| **Personal** | One user's progress and opinions | UserGame, UserTrophySet, UserTrophy, ProgressSnapshot, UserObjectiveProgress, UserTracker(Progress), notes, personal estimates | a `User` | canonical and knowledge data, by TrophyVault identity |
| **Provider/operational** | Traces of external communication | Provider, ImportRun, SyncRun, ExternalRecord, SyncError | the system | whatever it produced (for provenance) |

## Rules  (Decided)

1. **No mixing.** A record never carries both shared and personal facts. Example: Objective *"Study American Alligator"* (knowledge) vs.
   `UserObjectiveProgress` "studied: yes" (personal). The same applies to Trophy (canonical) vs. UserTrophy (earned at …).
2. **Direction of references.** Personal → shared, never shared → personal. Consequence: the same guide/tracker definition serves many users, and
   deleting a user cannot corrupt shared data.
3. **Estimates are derived personal data** (they depend on a user's progress), never a column on `Game`.
4. **Provider data is input, not model.** Raw provider records may be kept for traceability; domain tables hold normalized data
   ([`provider-boundary.md`](provider-boundary.md)).
5. **Third-party content is separable.** Imported copyrighted content carries provenance so it can be identified and removed independently of code and personal data.
6. **Export** is per class: a full TrophyVault export (JSON) contains all classes with their provenance, so the state can be handed to other software.
   Personal data is exported with the user; shared data may be exported by reference or in full (open: export profiles).

## Consequences for M0.3

The data model must make rule 2 enforceable (separate tables per class, foreign keys pointing in the allowed direction) and must
not use "shared" tables with a nullable `user_id`. How *deletion* and *history/versioning* work per class is open.
