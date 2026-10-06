# Acceptance scenarios A–H

Structural scenarios the model must support. Each is covered by tests with synthetic data (no third-party content). **Physical** = proven by tests on the migrated schema; **Planned** = needs tables of a later milestone, specified here as acceptance scenario.

| | Scenario | Status | Proof |
| --- | --- | --- | --- |
| A | One game with a PS4 release and a PS5 release and separate PS4 / PS5 trophy sets | Physical | `TrophyStructureTest::test_scenario_a_…` |
| B | One trophy set carrying several platforms, shared by several releases | Physical | `TrophyStructureTest::test_scenario_b_…` |
| C | One game with regional trophy stacks (NA/EU/JP sets on regional releases) | Physical | `TrophyStructureTest::test_scenario_c_…`, `TrophyRelationTest` (stack relations) |
| D | A game without Platinum | Physical | `TrophyStructureTest::test_scenario_d_…`; at most one platinum per set |
| E | Base group plus several add-on groups, with separate classification | Physical | `TrophyStructureTest::test_scenario_e_…` |
| F | A user has the Platinum but the set is not 100 % | Physical | `AccountProgressTest::test_scenario_f_…` — derived from earned rows, no stored completion |
| G | Objective tree with ≥ 4 levels | Planned (M4) | see below |
| H | Two PSN accounts with independent progress | Physical | `AccountProgressTest::test_scenario_h_…` |

## Scenario G — objective tree (acceptance scenario for M4)
Fixture (synthetic): guide `100 % Completion` → `Collectibles` → `Animals` → `Birds` → `Sample Bird 1…3` (5 levels), siblings ordered by `position`.
M4 must demonstrate with tests: (1) the whole subtree via `WITH RECURSIVE` returns 7 nodes in tree order; (2) ancestors of a leaf via recursive CTE; (3) a parent in another guide is rejected by the composite FK; (4) creating a cycle (`parent_id` = descendant) is rejected by the trigger; (5) reordering siblings inside one transaction passes the deferrable `UNIQUE NULLS NOT DISTINCT (guide_id, parent_id, position)`; (6) a completed leaf for user U does not change the objective row (progress lives in `user_objective_progress`); (7) retiring an objective in a guide version keeps the old version's tree reconstructable.

## Further structural cases covered
- Look-alike PS4/PS5 trophies are separate rows linked by `equivalent` (`TrophyRelationTest`).
- A release cannot be linked to a trophy set of another game (`TrophyStructureTest`).
- An edition of another game cannot be used by a release (`CatalogTest`).
- `A autopop B` and `B autopop A` cannot coexist; a pair cannot be both `autopop` and `partial_autopop` (`TrophyRelationTest`).
- An external id can be retired and reassigned (`ExternalIdentifierTest`).
- A deleted PSN account removes only its own progress, never the trophy definitions (`AccountProgressTest`).
