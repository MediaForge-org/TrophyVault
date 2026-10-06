# Architecture documentation (M0.2)

Status of this folder: **M0.2 — Domains & Product Boundaries.** It fixes vocabulary and boundaries and deliberately does **not**
define tables, keys, constraints or indexes. That was done in **M0.3 — Data Model**: see [`../data-model/`](../data-model/README.md). Statements here marked *Open* were closed there; where they still read as open,
the [resolution matrix](../data-model/open-questions-resolution.md) is authoritative.

| Document | Content |
| --- | --- |
| [`product-boundaries.md`](product-boundaries.md) | What TrophyVault is and is not |
| [`domain-map.md`](domain-map.md) | Domains, concepts, responsibilities, dependency rules, planned namespaces |
| [`terminology.md`](terminology.md) | Core terms and the distinctions that must not blur |
| [`data-classification.md`](data-classification.md) | Canonical / Knowledge / Personal / Provider data and who owns what |
| [`provider-boundary.md`](provider-boundary.md) | External providers, adapters, normalization, provenance, sync |
| [`knowledge-model.md`](knowledge-model.md) | Guides, recursive objectives, data-driven trackers |
| [`completion-semantics.md`](completion-semantics.md) | Platinum vs. trophy-set vs. ingame vs. full completion, status model |
| [`open-questions-m0.3.md`](open-questions-m0.3.md) | Everything deliberately left to the data-model package, plus review findings |

## Maturity labels

Every statement about the future carries one of these labels (or is clearly descriptive):

- **Decided** — settled by M0.2 (and recorded in an ADR where it matters). Changing it needs a new ADR.
- **Conceptual** — the idea is intended, the exact shape is not fixed. Example lists (relation kinds, objective types) are conceptual.
- **Open** — explicitly undecided; listed in `open-questions-m0.3.md`.

Related decisions: [ADR 0002](../decisions/0002-providers-do-not-define-the-domain.md) ·
[0003](../decisions/0003-canonical-knowledge-personal-data.md) · [0004](../decisions/0004-game-release-trophyset-distinct.md) ·
[0005](../decisions/0005-recursive-objectives.md) · [0006](../decisions/0006-platinum-is-not-completion.md)
