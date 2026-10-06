# Product boundaries

## What TrophyVault is  (Decided)

> A self-hosted completion platform that combines official trophy data, structured guide knowledge and personal in-game progress.

Three ingredients, always kept apart (see [`data-classification.md`](data-classification.md)):

1. **Official trophy data** — what the platform says exists (trophy sets, groups, trophies) and what a PSN account has earned.
2. **Structured guide knowledge** — how something is completed, as data (objective trees, trackers), not as one long text.
3. **Personal in-game progress** — what *this user* has done, including things no trophy records (collectibles, challenges, in-game trackers).

The point of the product: a game is **not** finished just because the Platinum popped. TrophyVault models the stricter
definitions players actually use (all trophy groups, in-game 100 %, custom goals) — see [`completion-semantics.md`](completion-semantics.md).

It is self-hosted and personal: one installation is run by its owner; there is no central service. Whether it supports one or several local users is **Open** (question 19).

## What TrophyVault is not  (Decided)

- **Not a replacement for PlayStation Network.** PSN remains the authoritative *source* of official trophy data and earned trophies; TrophyVault consumes it (through an adapter — see [`provider-boundary.md`](provider-boundary.md)).
- **Not a social network.** No feeds, friends, public profiles or leaderboards.
- **Not a game launcher or store.**
- **Not a general achievement SDK** for game developers.
- **Not a PSNProfiles clone.** Aggregated community statistics are not the goal; per-user completion depth is.
- **Not a one-off scraping script.** Imports are repeatable, idempotent and traceable (see [`provider-boundary.md`](provider-boundary.md)).

The architecture may allow later extensions, but M0/M1 must not carry scope creep. New product areas need an explicit decision first.

## Capabilities by horizon  (Conceptual)

| Horizon | Capability |
| --- | --- |
| M0 | Foundation, domain boundaries, data model |
| later | PSN sync, trophy library, guide/objective knowledge, trackers, personal completion |
| later | Planning/estimates, export (JSON first) |

Exact milestone assignment beyond M0 is not part of this document.

## Content and legal boundary  (Decided)

Code, personal data and imported third-party content (guide texts from other sites, artwork) are kept separate. Third-party
copyrighted content must be attributable to a source (provenance) and removable without touching code or personal data.
The project does not ship third-party guide content in the repository.
