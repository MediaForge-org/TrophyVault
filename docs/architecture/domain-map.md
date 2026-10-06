# Domain map

The domain map below is the agreed functional foundation (**Decided** as a set of domains and their responsibilities).
Individual concept names inside a domain are **Conceptual** until M0.3 turns them into a schema.

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
| Identity | personal | The local `User`, and `PsnAccount`: a linked PlayStation account. They are different concepts. The cardinality between them (and ownership of earned data) is **Open** (questions 18–19). |
| Catalog | canonical | What games exist: the work (`Game`), concrete published variants (`GameRelease`, `Edition`, `Platform`, `Region`), relations between games, and external identities. |
| Trophies | canonical | Official trophy structure: `TrophySet` → `TrophyGroup` → `Trophy`, plus relations between sets and between trophies. |
| Player Progress | personal | What a user earned and their personal relationship to games/sets; snapshots over time. |
| Knowledge | knowledge | How things are completed: guides (versioned), recursive objectives, tracker definitions, sources. |
| Personal Completion | personal | A user's progress against knowledge: objective progress, tracker instances and progress. |
| Sync / Providers | provider/operational | Getting external data in: provider definitions, import/sync runs, raw external records, errors. |
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
- **Export and Planning only read from the other domains** (Planning additionally persists its own derived personal data, e.g. estimates); no other domain depends on them.
- Cross-domain access goes through the other domain's public services/queries, not through its internals (Conceptual; see namespaces).

## Planned namespace structure  (Conceptual — not created in M0.2)

```text
app/Domain/
├── Identity/
├── Catalog/
├── Trophies/
├── PlayerProgress/
├── Knowledge/
├── PersonalCompletion/
├── Sync/            # incl. provider contracts; concrete adapters live in app/Domain/Sync or a separate app/Integrations (open, question 17; `app/Providers` is Laravel's service-provider folder and is not used for this)
└── Planning/
```

`Export` is an application capability rather than a domain with its own data; its location (`app/Export` vs. `app/Domain/Export`) is open.

**No empty directories or placeholder classes were added in M0.2.** A namespace is created together with its first real,
tested class (in M0.3 or later), so that structure follows code instead of pretending to exist. The Laravel default `app/Models`
is not extended in M0.2; whether Eloquent models live inside the domain folders is decided in M0.3 (see open questions).

## Review notes on the map

M0.2 reviewed the map and **kept it unchanged**. Findings (gaps, not contradictions) are recorded as open decisions in
[`open-questions-m0.3.md`](open-questions-m0.3.md): where Provenance lives, how Catalog links to Trophies, how earned trophies
relate to `PsnAccount` vs. `User`, and where provider adapters live.
