# 0010 — Provenance and sync/import model (cross-cutting)

Status: accepted (design; physical tables arrive with M2) · Date: 2026-10-07 · Resolves M0.3 open questions 14, 15, 17; defers 16

## Context
M0.2 listed `Provenance/Source` under *Knowledge*, but canonical data (games, trophy sets) needs provenance too, and a dependency
canonical → knowledge would violate [ADR 0003](0003-canonical-knowledge-personal-data.md). M0.2's sync concepts (`Import`, `ImportRun`, `SyncRun`,
`ExternalRecord`, `SyncError`) also overlap.

## Decision

### Provenance is cross-cutting and points *at* data, never the other way round
Provenance rows reference their subjects; subjects (games, trophy sets, guide versions, …) hold no provenance FK. (`sources` is a leaf that depends only on `providers`; a row may cite a source directly where one origin is intrinsic, e.g. `guide_time_estimates.source_id`, without creating a cycle.) This keeps the dependency direction clean:
`provenance → canonical | knowledge`, never `canonical → provenance`.

Planned tables (logical v1; created in M2 when the first importer needs them):

| Table | Purpose |
| --- | --- |
| `sources` | A citable origin: `provider_code`, `kind` (api, web_page, file, manual), `url`, `title`, `content_license` (unknown/own/permitted/restricted). Guides and imports cite sources. |
| `external_records` | What a provider *returned* for one thing, observed over time: `(provider_code, record_type, external_key)` unique; `content_hash`, `source_version`, `retrieved_at`/`first_seen_at`/`last_seen_at`, `missing_since`, optional bounded `payload jsonb` + `payload_purged_at`, `last_import_run_id`. |
| `provenance` | Links a normalised row to where it came from: exclusive arc over subjects (`game_id`, `game_release_id`, `trophy_set_id`, `trophy_group_id`, `trophy_id`, `guide_version_id`, `objective_id`, …) + `external_record_id` and/or `source_id`, `role` (imported, curated, confirmed), `importer`/`importer_version`, `confidence`, `fields text[]` (which attributes it covers), `created_at`. |

**Granularity: per row, optionally per attribute group (`fields`) — not per column.** A game row created by an import has one provenance row; a manually curated
group classification (`classification_origin = manual`) is recorded on the row itself, because it influences re-imports.

### Sync = one run model
`ImportRun` and `SyncRun` are the same thing with a different `run_type`; `Import` (a definition of an import) has no data of its own in v1 and becomes configuration.

| Table | Purpose |
| --- | --- |
| `import_runs` | One execution: `provider_code`, `run_type` (sync, import, backfill), `scope_key` + `scope` (e.g. the PSN account, a guide pack), optional `psn_account_id`, `trigger`, `status` (pending, running, succeeded, partial, failed, cancelled), `started_at`/`finished_at`, `importer_version`, `stats jsonb`, `retry_of_run_id`. A partial unique index allows **one running run per `(provider_code, scope_key)`**. |
| `import_errors` | Problems inside a run: `import_run_id`, optional `external_record_id`, `code`, sanitised `message`, `retryable`, `context jsonb` (redacted), `occurred_at`. Never contains credentials. |

### Idempotency, retries, change detection
- Upserts key on stable identity: external identifier → entity, `(trophy_set_id, set_key)` for groups/trophies, `(psn_account_id, trophy_id)` for earned rows. Re-running a run produces the same rows.
- `external_records.content_hash` unchanged ⇒ skip normalisation. Changed ⇒ normalise, update `updated_at`, write provenance.
- A failed run is re-run (`retry_of_run_id`); partial failure = status `partial` + `import_errors`, successfully imported rows stay.
- Things that vanish upstream get `missing_since`, they are not deleted.

### Raw payload retention (conceptual, M2 implements)
Hash and metadata are kept indefinitely; the JSONB `payload` is kept for the most recent N versions per record and for records with unresolved errors, then nulled (`payload_purged_at`) by a scheduled job. Adapters redact secrets before anything is stored; payloads are never exposed in exports.

### Conflict resolution between providers (open → deferred)
Recorded principle: attribute precedence follows `manual/curated` > `platform` (psn) > `third_party` > `community`, implemented in normalisation code, with all candidates visible through provenance. The mechanism is **deferred to M2/M3** (needs real multi-provider data).

### Where code lives
Provider adapters/DTOs in `app/Integrations/<Provider>/`; contracts, normalisation and run orchestration in `app/Domain/Sync/` (created with first code). Domain code never imports from `app/Integrations`.

## Consequences
- Removing/purging provenance never touches domain rows.
- The arc table grows one column per subject kind (same trade-off as [ADR 0009](0009-external-identifiers.md)).
- Imported third-party *content* (guide text) additionally carries `sources.content_license`; it is local data and never committed to the repository ([`security-and-performance.md`](../data-model/security-and-performance.md)).
