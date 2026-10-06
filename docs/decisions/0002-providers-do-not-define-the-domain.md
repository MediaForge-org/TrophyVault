# 0002 — External providers do not define the internal domain

Status: accepted · Date: 2026-10-06

## Context
TrophyVault will consume PSN and possibly PSNProfiles, PowerPyx, PlayStationTrophies, manual and community data. Mirroring the
current PSN API structure internally is the quickest start, but couples the whole model to one vendor's shapes and ids.

## Decision
Data flows `External Provider → Provider Adapter → Normalization → TrophyVault Domain`. Provider identifiers (e.g. PSN
`npCommunicationId`) are *external identities* stored as external identifiers; TrophyVault entities have their own identity. Only adapters know
provider formats; domain code never depends on provider DTOs. No provider is architecturally privileged.

## Consequences
- An API change at a provider affects its adapter and identifier mappings, not the domain model.
- Imports need a stable external-id → internal-id mapping (basis for idempotency).
- Some duplication/translation work is accepted for independence.
- Details: [`provider-boundary.md`](../architecture/provider-boundary.md). Provenance storage was decided in M0.3 ([ADR 0010](0010-provenance-and-sync-model.md)); conflict resolution is deferred to M2/M3.
