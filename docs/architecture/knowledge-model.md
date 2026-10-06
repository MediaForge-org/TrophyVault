# Knowledge model: guides, objectives, trackers

([ADR 0005](../decisions/0005-recursive-objectives.md) — **Decided**: objectives are recursive. Everything else here is **Conceptual**.)

## Guides

Guides exist on at least three levels — **game guide**, **trophy guide**, **objective guide** — but the model must not be limited to
three levels. A trophy never has "one long guide text" as its only representation; guide content can be attached to any node of the objective tree.
Guides are versioned (`GuideVersion`) and carry provenance.

## Objective tree

An `Objective` is a node that can have any number of children, to any depth. Examples:

```text
Zoologist                          Gold Rush                        100 % Completion
└── Animals                        ├── Chapter 1                    ├── Story
    ├── American Alligator         │   ├── Mission A                ├── Collectibles
    ├── Nine-Banded Armadillo      │   │   ├── Gold Requirement A   ├── Challenges
    └── American Badger            │   │   └── Gold Requirement B   ├── Side Missions
                                   │   └── Mission B                ├── Online
                                   ├── Chapter 2                    └── Miscellaneous
                                   └── Epilogue
```

- A node's *meaning* comes from its **type** (trophy, mission, collectible, challenge, animal, location, activity, requirement, counter,
  checklist, chapter, category, custom — extensible, not a closed list).
- **No table per type, and no wide table with dozens of nullable special columns.** Type-specific details are data of the node;
  how that is stored (normalized columns, JSONB, or a mix) is decided in M0.3.
- Beyond the tree, `ObjectiveRelation` can express non-hierarchical links (e.g. a trophy objective that is satisfied by a subtree).
  The relation kinds are open.
- Objectives are **general knowledge**. A user's state for an objective is `UserObjectiveProgress` (personal); see [`data-classification.md`](data-classification.md).

## Trackers

TrophyVault must not need PHP code per game. A `TrackerDefinition` describes, as data, what is tracked and how progress is computed.
Conceptual tracker kinds: `boolean`, `counter`, `percentage`, `checklist`, `hierarchical`, `derived`.

Example (RDR2 challenges, expressed as data rather than code):

```text
Challenges
├── Bandit  ├── Explorer  ├── Gambler  ├── Herbalist  ├── Horseman
└── Master Hunter · Sharpshooter · Survivalist · Weapons Expert
```

A new game checklist should be addable as a data/guide pack. A user's instance is `UserTracker` with `UserTrackerProgress`.
Whether tracker definitions *are* objective trees, *reference* them, or are a separate structure is **open** (M0.3).
