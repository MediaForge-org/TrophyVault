# Provider boundary

([ADR 0002](../decisions/0002-providers-do-not-define-the-domain.md) — **Decided**)

> External APIs do not define the TrophyVault domain.

```text
External Provider
       ↓
Provider Adapter     — fetches, authenticates, rate-limits, knows the provider's format
       ↓
Normalization        — translates into TrophyVault domain concepts (and records provenance)
       ↓
TrophyVault Domain   — knows nothing about provider formats
```

## Rules  (Decided)

- **Provider ids are external identities.** A PSN `npCommunicationId`, title id, or a PSNProfiles slug identifies a thing *in that provider's world*.
  TrophyVault entities have their own identity; provider ids are attached as `ExternalIdentifier`s (many per entity, possibly from several providers).
  Consequence: if Sony changes its API or id scheme, adapters and identifier mappings change — the domain does not.
- **No provider dictates structure.** The domain does not mirror the PSN response shape. Example: PSN's trophy "groups" are stored as structure,
  while TrophyVault's own classification (base / dlc / …) is a separate, TrophyVault-owned attribute.
- **Only adapters import provider SDKs/HTTP clients/DTOs.** Domain and application code never type-hint provider DTOs.
- **Providers are interchangeable and combinable.** Several providers may describe the same entity; no single one (including PSN) is architecturally privileged. PSN is the authoritative *source* for official trophy data and earned trophies, but that is a statement about data trust, not about structure: the domain model still does not mirror PSN.
  How conflicts between providers are resolved: principle recorded, mechanism **Deferred** to M2/M3 ([ADR 0010](../decisions/0010-provenance-and-sync-model.md)).
- **Manual and community data are providers too** (`Manual`, `Community`), so they carry provenance like any other source.

## Provenance  (Conceptual)

For anything imported, TrophyVault must be able to answer *"where does this come from?"*. The information needed (not necessarily these columns):

```text
provider · external_id · source_url · retrieved_at · source_version / content hash
```

**Decided in M0.3** ([ADR 0010](../decisions/0010-provenance-and-sync-model.md)): per row (optionally per attribute group), via cross-cutting `sources`/`external_records`/`provenance` tables pointing at the rows they describe.

## Sync and imports  (Decided as requirements, Conceptual as structure)

Imports and syncs must be:

- **idempotent** — repeating a run produces no duplicates and no unnecessary changes;
- **repeatable and retryable** — runs can fail partially and be re-run; failures are recorded (`SyncError`), not swallowed;
- **traceable** — each run is recorded (`ImportRun`; M0.3 merged `SyncRun` into it), changes can be attributed to a run;
- **change-aware** — a changed source (version/hash) is detectable.

Idempotency relies on stable mapping from external identity to internal identity; that is why external ids must be unique per (provider, kind)
— implemented as `UNIQUE (provider_code, namespace, value) WHERE retired_at IS NULL` on `external_identifiers` ([ADR 0009](../decisions/0009-external-identifiers.md)).

## History  (Conceptual)

Traceable history without event sourcing. Candidate mechanisms: `ProgressSnapshot`, `ImportRun`, `GuideVersion`, source revisions.
No general-purpose event store.

## Out of scope in M0.2

No PSN authentication/NPSSO, HTTP client, trophy sync, PSNProfiles, scraping or guide providers. Credentials/tokens remain
git-ignored and never enter the repository.
