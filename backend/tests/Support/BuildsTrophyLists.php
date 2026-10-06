<?php

namespace Tests\Support;

use App\Models\Catalog\Game;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophySet;

trait BuildsTrophyLists
{
    /**
     * Creates a trophy list with one 'default' group holding one trophy per given grade.
     *
     * @param  list<string>  $grades
     * @param  list<string>  $platforms
     * @return array{set: TrophySet, group: TrophyGroup, trophies: list<Trophy>}
     */
    protected function trophyList(?Game $game = null, array $grades = ['bronze', 'bronze', 'silver', 'platinum'], array $platforms = ['ps4']): array
    {
        $set = TrophySet::factory()->for($game ?? Game::factory()->create())->onPlatforms($platforms)->create();
        $group = TrophyGroup::factory()->create(['trophy_set_id' => $set->id]);

        return ['set' => $set, 'group' => $group, 'trophies' => $this->addTrophies($set, $group, $grades)];
    }

    /**
     * @param  list<string>  $grades
     * @return list<Trophy>
     */
    protected function addTrophies(TrophySet $set, TrophyGroup $group, array $grades, int $startKey = 0): array
    {
        $trophies = [];
        foreach ($grades as $offset => $grade) {
            $trophies[] = Trophy::factory()->create([
                'trophy_set_id' => $set->id,
                'trophy_group_id' => $group->id,
                'set_key' => $startKey + $offset,
                'ordinal' => $startKey + $offset,
                'grade' => $grade,
            ]);
        }

        return $trophies;
    }
}
