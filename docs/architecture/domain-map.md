# Domain map

The domain map below is the agreed functional foundation (**Decided** as a set of domains and their responsibilities).
Individual concept names inside a domain were **Conceptual** in M0.2. **M0.3 turned them into a schema** (see [`../data-model/`](../data-model/README.md)); where the physical model deviates from these names it is listed in
[`../data-model/logical-model.md`](../data-model/logical-model.md#m02-concept--m03-table-mapping). The map below is kept as the M0.2 baseline.

> **M0.3 refinements of this map** (decided, ADR-backed): `Provenance/Source` is *cross-cutting* (not Knowledge) — [ADR 0010](../decisions/0010-provenance-and-sync-model.md);
> `UserTrophySet`/`UserTrophy` became account-owned `psn_account_trophy_sets`/`psn_account_trophies` — [ADR 0012](../decisions/0012-account-owned-progress.md);
> `UserTracker`+`UserTrackerProgress` merged; `Import`+`ImportRun`+`SyncRun` merged into one run model.

```text
IDENTITY            User · PsnAccount
CATALOG             Game · GameRelease · Edition · Platform · Region · GameRelation · ExternalIdentifier
TROPHIES            TrophySet · TrophyGroup · Trophy · TrophySetRelation · TrophyRelation
PLAYER PROGRESS     UserGame · UserTrophySet · UserTrophy · ProgressSnapshot
KNOWLEDGE           Guide · GuideVersion · Objective · ObjectiveRelation · TrackerDefinition · Provenance/Source
PERSONAL COMPLETION UserObjectiveProgress · UserTracker · UserTrackerProgress
SYNC / PROVIDERS    Provider · Import · ImportRun · SyncRun · ExternalRecord · SyncError
PLANNING            Estimate · CompletionTarget · CompletionPlan · Recommendation
EXPORT              Export · ExportProfile · Report
```

## Responsibilities

| Domain | Data class | Responsibility |
| --- | --- | --- |
| Identity | personal | The local `User`, and `PsnAccount`: a linked PlayStation account. They are different concepts. **Decided in M0.3:** a user has 0..n PSN accounts, an account belongs to exactly one user, earned data belongs to the account ([ADR 0012](../decisions/0012-account-owned-progress.md)). |
| Catalog | canonical | What games exist: the work (`Game`), concrete published variants (`GameRelease`, `Edition`, `Platform`, `Region`), relations between games, and external identities. |
| Trophies | canonical | Official trophy structure: `TrophySet` → `TrophyGroup` → `Trophy`, plus relations between sets and between trophies. |
| Player Progress | personal | What a user earned and their personal relationship to games/sets; snapshots over time. |
| Knowledge | knowledge | How things are completed: guides (versioned), recursive objectives, tracker definitions. (`Source`/`Provenance` moved to the cross-cutting Sync/Providers area in M0.3.) |
| Personal Completion | personal | A user's progress against knowledge: objective progress, tracker instances and progress. |
| Sync / Providers | provider/operational | Getting external data in: provider definitions, import/sync runs, raw external records, errors; plus the cross-cutting sources and provenance that cite where data came from. |
| Planning | personal (derived) | Estimates, completion targets/plans, recommendations. Derived from the other domains, never stored as a field on `Game`. |
| Export | cross-cutting capability | Producing JSON (primary), CSV, HTML, PDF, ZIP from the other domains. Reads only. |

## Dependency rules  (Decided)

Allowed direction of knowledge — a domain may reference those to its right/below, never the reverse:

```text
Export, Planning
      ↓ read
Personal Completion, Player Progress   (personal data)
      ↓ reference by identity
Knowledge, Trophies, Catalog           (shared data)
      ↑ written by
Sync / Providers → (adapters + normalization) → Catalog / Trophies / Knowledge / Player Progress
```

- **Canonical and Knowledge data never reference personal data.** A `Guide` knows nothing about users.
- **Personal data references shared data by TrophyVault identity**, never by provider identifier.
- **Provider code (adapters) is the only code that knows provider formats.** The rest of the application sees normalized domain data
  ([ADR 0002](../decisions/0002-providers-do-not-define-the-domain.md)).
- **Provenance/Source rows point at canonical and knowledge rows; those hold no reference back to provenance** ([ADR 0010](../decisions/0010-provenance-and-sync-model.md)). `sources` depends only on `providers`, so a row may cite a source directly where one origin is intrinsic without creating a cycle.
- **Export and Planning only read from the other domains** (Planning additionally persists its own derived personal data, e.g. estimates); no other domain depends on them.
- Cross-domain access goes through the other domain's public services/queries, not through its internals (Conceptual; see namespaces).

## Namespace structure  (**Decided in M0.3**, see [`../data-model/conventions.md`](../data-model/conventions.md#code-placement-adr-0008-q8))

```text
app/Models/<Domain>/        Eloquent models (exist: Identity, Catalog, Trophies, Sync)
app/Enums/                  PHP enums mirroring CHECK constraints
app/Domain/<Domain>/        services/value objects/use cases — created together with the first real class
│   Identity · Catalog · Trophies · PlayerProgress · Knowledge · PersonalCompletion · Sync · Planning
app/Integrations/<Provider>/ provider adapters and DTOs (domain code never imports from here)
```

`Export` is an application capability rather than a domain with its own data; it gets no persistent model in v1 (optional `export_runs` history) and its code location is decided with the export milestone.

**No empty directories or placeholder classes** exist: a namespace is created together with its first real, tested class, so that structure follows code instead of pretending to exist.
M0.3 decided that Eloquent models live in `app/Models/<Domain>/` (Laravel convention, automatic factory resolution) and that `app/Domain/<Domain>/` holds services/value objects once they exist.

## Review notes on the map

M0.2 reviewed the map and **kept it unchanged**; the gaps it found (where Provenance lives, how Catalog links to Trophies, earned trophies on `PsnAccount` vs. `User`, where adapters live) were
recorded in [`open-questions-m0.3.md`](open-questions-m0.3.md) and **closed in M0.3** ([resolution matrix](../data-model/open-questions-resolution.md)): the map's domains and responsibilities stand; only the concept-to-table details listed above changed.
