# Core terminology

Terms are used with exactly these meanings in code, docs and issues.

## Catalog and trophies

| Term | Meaning | Not to be confused with |
| --- | --- | --- |
| **Game** | The work, independent of platform release. "Grand Theft Auto V" is one Game. | A platform release; a trophy set |
| **GameRelease** | One concrete published variant of a Game (platform, region, edition, …). Cardinality towards Platform/Region/Edition is **Open** (M0.3). | A Game; a TrophySet |
| **Edition** | Standard, Deluxe, Complete, … A release/edition concept, **not** a separate Game. | A Game |
| **Platform / Region** | Where/for whom a release exists (PS3, PS4, PS5, …; regions). | |
| **GameRelation** | A relation between Games: e.g. `remake_of`, `remaster_of`, `sequel_to`, `prequel_to`, `collection_contains`, `related` (Conceptual, not a fixed enum). | |
| **ExternalIdentifier** | An identity assigned by an outside system (PSN title id, store id, …). Never the primary identity. | A TrophyVault id |
| **TrophySet** | One independent PlayStation trophy list. A PSN `npCommunicationId` is *an external identifier of* a set. | A Game |
| **TrophyGroup** | Structural subdivision of a TrophySet as defined by the platform (`default` group, numbered groups). **Not automatically DLC.** | DLC |
| **Trophy** | One trophy, in the context of its set/group. PS4 and PS5 trophies that look identical are **separate** trophies. | |
| **TrophySetRelation** | Relation between sets: `platform_stack`, `regional_stack`, `remaster_stack`, `equivalent`, `autopop_source`, `autopop_target`, `partial_autopop`, `related` (Conceptual). | A Game relation |
| **TrophyRelation** | Relation between trophies: `equivalent`, `similar`, `replaces`, `related` (Conceptual). "Equivalent" ≠ "same record". | |
| **Group classification** | TrophyVault's own semantic label for a group: `base`, `dlc`, `expansion`, `free_update`, `online`, `challenge`, `other`, `unknown` (Conceptual). Stored separately from the provider structure. | The provider's group id |
| **Auto-pop** | A relation between sets (or individual trophies) in which earning one grants another. **Not** a boolean on Game. | |

## Knowledge

| Term | Meaning |
| --- | --- |
| **Guide** | Structured knowledge about completing something. Levels: game guide, trophy guide, objective guide — but the hierarchy is not limited to three levels. |
| **GuideVersion** | A revision of a guide, so changes are traceable. |
| **Objective** | A node in a recursive tree of "things to do". Meaning is given by its *type* (trophy, mission, collectible, challenge, animal, location, activity, requirement, counter, checklist, chapter, category, custom — Conceptual, extensible). Objectives are general knowledge. |
| **ObjectiveRelation** | Relation between objectives beyond the tree (e.g. requires, supersedes) — Conceptual. |
| **TrackerDefinition** | Data-driven definition of a tracker (boolean, counter, percentage, checklist, hierarchical, derived — Conceptual) so a new game checklist needs data, not code. |
| **Source / Provenance** | Where a piece of information came from: provider, external id, URL, retrieval time, version/hash (Conceptual shape). |

## Personal

| Term | Meaning |
| --- | --- |
| **User** | Local TrophyVault user. |
| **PsnAccount** | A linked PlayStation account. Not the same thing as a User. |
| **UserGame / UserTrophySet / UserTrophy** | A user's personal relationship to a game / trophy set / earned trophy (with timestamp). |
| **ProgressSnapshot** | A point-in-time record of progress for history. Not event sourcing. |
| **UserObjectiveProgress** | The user's state for an Objective. **Example:** Objective "Study American Alligator" is knowledge; "this user has studied it" is `UserObjectiveProgress`. |
| **UserTracker / UserTrackerProgress** | A user's instance of a TrackerDefinition and its progress. |

## Completion and planning

| Term | Meaning |
| --- | --- |
| **Platinum Status** | Whether the Platinum trophy is earned — or `not_applicable` when the set has none. |
| **Trophy Set Completion** | All trophies of a set earned (100 %). |
| **Base Trophy Completion** | All trophies of the base group(s). |
| **All Trophy Groups Completion** | All groups (base + DLC/other) of the set(s). |
| **Ingame Completion** | The game's own 100 % notion (tracker-based; not trophy-derived). |
| **Custom Completion** | A user-defined target. |
| **TrophyVault Full Completion** | The combined, configurable definition. See [`completion-semantics.md`](completion-semantics.md). |
| **Estimate** | A time estimate for a target with min/max, confidence, method, calculation time and inputs. Never a field on Game. |
| **CompletionTarget** | What an estimate/plan aims at (Platinum, Trophy 100 %, Ingame 100 %, Objective, Full Completion). |

## Sync

| Term | Meaning |
| --- | --- |
| **Provider** | An external data source (PSN, PSNProfiles, PowerPyx, PlayStationTrophies, Manual, Community). |
| **Adapter** | Provider-specific code that fetches and translates provider data. |
| **Normalization** | Translating provider shapes into domain concepts. |
| **ExternalRecord** | A stored raw/semi-raw provider record used for traceability and change detection. |
| **ImportRun / SyncRun / SyncError** | One execution of an import/sync, and what went wrong during it. |
