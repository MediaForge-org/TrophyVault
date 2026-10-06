# 0012 — Earned trophy progress belongs to the PSN account; identity and multi-user

Status: accepted · Date: 2026-10-07 · Resolves M0.3 open questions 18, 19, 20, 21

## Context
M0.2 named `UserGame`, `UserTrophySet`, `UserTrophy`. Earned trophies originate from, and are authoritative at, a PSN account. A person may have several PSN accounts
(regions, old accounts) and several local users may exist. Progress that is merged onto the `User` loses the origin and makes account change/unlink destructive.

## Decision
1. **`users` and `psn_accounts` are separate; a user has 0..n PSN accounts, a PSN account belongs to exactly one user** (`psn_accounts.user_id NOT NULL`, `UNIQUE (psn_account_id)` globally). Deleting a user cascades to its accounts and their progress.
2. **Earned state hangs on the PSN account:**
   - `psn_account_trophy_sets (psn_account_id, trophy_set_id)` — this account has this trophy list (library membership + `first_seen_at`, `last_synced_at`, provider-reported progress for cross-checking).
   - `psn_account_trophies (psn_account_id, trophy_id, trophy_set_id, earned_at)` — **one row per earned trophy; absence = not earned.** `earned_at NULL` = earned at an unknown time.
   - Composite FKs guarantee the trophy belongs to the stated set and the account has that set.
   M0.2's `UserTrophySet`/`UserTrophy` are therefore **superseded by these two tables**; "user-level" progress is the aggregation over the user's accounts.
3. **Personal data that is not PSN-originated hangs on the user** (planned: `user_games`, `user_objective_progress`, `user_tracker_progress`, notes, estimates). Manual completion never writes into account tables.
4. **Completion is derived, not stored** (earned count vs. trophy count per set/group, platinum earned, etc.). `psn_account_trophy_sets.provider_reported_progress` exists only to cross-check; no `completed`/`percent` cache columns. A materialised cache may be added later *if measured necessary* (derived table, rebuildable).
5. **Several accounts / sets (Q21 rule):** completion is computed per `(account, trophy set)`. A game's trophy completion for a user considers the user's accounts' sets as follows: sets connected by stack/equivalence relations (`platform_stack`, `regional_stack`, `remaster_stack`, `equivalent`) are *alternatives* — one complete member satisfies the group; unrelated sets of the same game are all required; a target of `autopop` is satisfied when its source is complete. Across several accounts the best result counts. The calculation engine is later work; the schema supports it without change.
6. **Achievement dimensions vs lifecycle (Q20):** platinum / trophy 100 % / all groups / ingame 100 % / full completion are derived; the personal *intent* (`not_started | backlog | playing | paused | abandoned`) is stored on `user_games` (planned M1), keyed `(user_id, game_id)`.
7. **Multi-user (Q19):** the schema is multi-user capable from day one (all personal tables are keyed by user or by account owned by a user). The first release can run with a single user and no registration UI; authentication is a separate decision, not blocked by data.
8. **Credentials** never sit in `psn_accounts`; they go into a separate encrypted table (planned `psn_account_credentials`, M2) — see [ADR 0008](0008-storage-conventions.md).
9. **History:** trophy timelines are derived from `earned_at`; a `progress_snapshots` table (account-level, per sync) is only planned for values that are *not* derivable later (e.g. completion as of the moment before a DLC list expanded) and is deferred until a feature needs it.

## Application API
`PsnAccount::holdTrophySet()` and `PsnAccount::earn()` are the supported ways to write account progress: `earn()` always files a trophy under *its own* set, adds the set to the library once, is idempotent (a repeated call updates `earned_at`) and normalises instants to UTC with offset. The composite foreign keys remain the actual guarantee when someone bypasses the API.

## Consequences
- Unlinking/relinking an account is a clean operation on that account's rows.
- A user view needs a join `users → psn_accounts → psn_account_trophies`; indexes support it (`psn_account_id, trophy_set_id`).
- Sharing one PSN account between two local users is not possible (unique account id) — intended.
- No "user trophy" table exists; documentation and code must say *account trophies*.
