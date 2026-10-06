# 0009 — External identifiers and providers

Status: accepted · Date: 2026-10-07 · Resolves M0.3 open questions 7 (uniqueness), 15 (identifier part)

## Context
TrophyVault ids are never provider ids ([ADR 0002](0002-providers-do-not-define-the-domain.md)). We still need to map many provider ids
(PSN `npCommunicationId`, `CUSA…`/`PPSA…` title ids, guide-site slugs) onto our entities, possibly several per entity and from several providers,
with ids that can be reassigned by the provider. Options:

| Option | Integrity | Ergonomics | Notes |
| --- | --- | --- | --- |
| A. One polymorphic table `(entity_type, entity_id, …)` | **no foreign key**, orphans possible | simple | rejected: weak integrity |
| B. One identifier table per entity kind | real FKs | N tables with identical shape, no single place for a global uniqueness rule | acceptable but repetitive |
| C. Provider columns on entities (`games.psn_id`) | FK-free values, no history | trivial | rejected: couples the entity to one provider, cannot hold several ids |
| **D. One table with an exclusive arc**: nullable FK column per entity kind + `CHECK (num_nonnulls(...) = 1)` | **real FKs**, one table | one `hasMany` per model | **chosen** |

## Decision
`external_identifiers(provider_code → providers, namespace, value, game_id | game_release_id | trophy_set_id, retired_at)`.
- **Exactly one** entity FK is set (`CHECK num_nonnulls(...) = 1`); each is a real foreign key with `ON DELETE CASCADE`.
- `namespace` distinguishes id kinds of one provider (`np_communication_id`, `title_id`, `slug`) — the same value in different namespaces/providers is legal.
- **Uniqueness:** `UNIQUE (provider_code, namespace, value) WHERE retired_at IS NULL`: an active external id identifies exactly one entity. Retiring a mapping frees the id for reassignment while keeping history.
- Adding a new identifiable entity kind later = add one nullable FK column, extend the CHECK/partial indexes (a migration), no data migration.
- `providers(code, name, kind)` is reference data (`psn`, `psnprofiles`, `powerpyx`, `playstationtrophies`, `community`, `manual`). A provider row says **nothing about whether a connector exists**; availability of adapters is code (see [`provider-boundary.md`](../architecture/provider-boundary.md)), not data.
- **Exception, deliberately:** `psn_accounts.psn_account_id` is a column, because `psn_accounts` is PSN-specific by nature (account identity, [ADR 0012](0012-account-owned-progress.md)).
- **In-list keys** (`trophy_groups.set_key`, `trophies.set_key`) are *not* external identifiers: they are the addresses of a group/trophy inside its trophy list as defined by the list itself, scoped by the owning set. If another provider numbers things differently, the mapping goes through relations/identifiers later.
- Trophy-level external identifiers are not needed in v1; the arc can be extended (`trophy_id`) if a provider requires it.

## Consequences
- Lookup by provider id is one index probe; resolving to the entity is one FK column read.
- The table gains a column per identifiable entity kind (currently three); documented as a known trade-off.
- Namespace names are conventions enforced by format check only (`^[a-z][a-z0-9_]*$`); the allowed namespaces per provider live in adapter code. Values must not contain any whitespace (regex CHECK) so a tab/newline cannot create a second spelling of one identifier.
- Always resolve identifiers with `ExternalIdentifier::active()`: the unique index is partial (`retired_at IS NULL`) and only queries repeating that predicate can use it.
