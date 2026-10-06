# Documentation

Documentation is kept small and written alongside the code.

- [`decisions/0001-foundation.md`](decisions/0001-foundation.md) — M0.1 foundation decisions and the reasoning behind them
- [`architecture/`](architecture/README.md) — M0.2 domain map, product boundaries, terminology, data classification, provider boundary, completion semantics, open questions for M0.3
- [`decisions/0002`–`0006`](decisions/) — M0.2 architecture decision records (providers, data classes, Game/Release/TrophySet, recursive objectives, completion)
- [`data-model/`](data-model/README.md) — M0.3 PostgreSQL data model: conventions, logical v1 model, ER diagrams, data dictionary, indexes/constraints, migration roadmap, scenarios, security/performance, database safety, resolution of the 27 open questions
- [`decisions/0007`–`0014`](decisions/) — M0.3 ADRs (UUIDv7, storage conventions, external identifiers, provenance/sync, catalog↔trophy structure, account-owned progress, objectives/trackers/guides, database safety)
- Project overview, setup and commands: [`../README.md`](../README.md)
- Rules for AI assistants (incl. git rules): [`../CLAUDE.md`](../CLAUDE.md)

New significant decisions go into `docs/decisions/NNNN-title.md` (context, decision, consequences).
