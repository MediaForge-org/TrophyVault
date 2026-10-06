<?php

namespace Tests\Integration\Trophies;

use App\Enums\TrophyRelationKind;
use App\Enums\TrophySetRelationKind;
use App\Models\Trophies\TrophyRelation;
use App\Models\Trophies\TrophySet;
use App\Models\Trophies\TrophySetRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

class TrophyRelationTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    public function test_a_set_cannot_relate_to_itself(): void
    {
        $set = TrophySet::factory()->create();

        $this->assertViolation(self::CHECK, fn () => TrophySetRelation::link($set, $set, TrophySetRelationKind::Related));
        $this->assertViolation(self::CHECK, fn () => TrophySetRelation::link($set, $set, TrophySetRelationKind::Autopop));
    }

    public function test_stacks_are_symmetric_and_stored_once(): void
    {
        [$a, $b] = [TrophySet::factory()->create(), TrophySet::factory()->create()];
        $relation = TrophySetRelation::link($b, $a, TrophySetRelationKind::PlatformStack);

        $this->assertLessThan(0, strcmp($relation->from_trophy_set_id, $relation->to_trophy_set_id));
        $this->assertViolation(self::UNIQUE, fn () => TrophySetRelation::link($a, $b, TrophySetRelationKind::PlatformStack));
    }

    public function test_autopop_is_directed_and_cannot_be_contradictory(): void
    {
        [$source, $target] = [TrophySet::factory()->create(), TrophySet::factory()->create()];
        $relation = TrophySetRelation::link($source, $target, TrophySetRelationKind::Autopop);

        $this->assertSame($source->id, $relation->from_trophy_set_id);
        $this->assertSame($target->id, $relation->to_trophy_set_id);
        // A autopops B and B autopops A makes no sense.
        $this->assertViolation(self::UNIQUE, fn () => TrophySetRelation::link($target, $source, TrophySetRelationKind::Autopop));
    }

    public function test_a_pair_is_either_fully_or_partially_autopopped(): void
    {
        [$source, $target] = [TrophySet::factory()->create(), TrophySet::factory()->create()];
        TrophySetRelation::link($source, $target, TrophySetRelationKind::PartialAutopop);

        $this->assertViolation(self::UNIQUE, fn () => TrophySetRelation::link($source, $target, TrophySetRelationKind::Autopop));
        $this->assertViolation(self::UNIQUE, fn () => TrophySetRelation::link($target, $source, TrophySetRelationKind::Autopop));
    }

    public function test_a_pair_may_be_related_in_several_independent_ways(): void
    {
        [$a, $b] = [TrophySet::factory()->create(), TrophySet::factory()->create()];
        TrophySetRelation::link($a, $b, TrophySetRelationKind::PlatformStack);
        TrophySetRelation::link($a, $b, TrophySetRelationKind::Equivalent);
        TrophySetRelation::link($a, $b, TrophySetRelationKind::Autopop);

        $this->assertSame(3, TrophySetRelation::query()->count());
    }

    public function test_trophy_relations_reject_self_references_duplicates_and_unknown_kinds(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'bronze']);
        [$one, $two] = $list['trophies'];

        $this->assertViolation(self::CHECK, fn () => TrophyRelation::link($one, $one, TrophyRelationKind::Equivalent));
        TrophyRelation::link($one, $two, TrophyRelationKind::Equivalent);
        $this->assertViolation(self::UNIQUE, fn () => TrophyRelation::link($two, $one, TrophyRelationKind::Equivalent));
        $this->assertViolation(self::CHECK, fn () => DB::table('trophy_relations')->insert([
            'from_trophy_id' => $one->id, 'to_trophy_id' => $two->id, 'kind' => 'identical',
        ]));
    }

    public function test_equivalent_trophies_of_different_sets_stay_separate_records(): void
    {
        $ps4 = $this->trophyList(platforms: ['ps4']);
        $ps5 = $this->trophyList(platforms: ['ps5']);
        TrophyRelation::link($ps4['trophies'][0], $ps5['trophies'][0], TrophyRelationKind::Equivalent);

        $this->assertSame(1, TrophyRelation::query()->count());
        $this->assertNotSame($ps4['trophies'][0]->id, $ps5['trophies'][0]->id);
        $this->assertSame(8, DB::table('trophies')->count());
    }

    public function test_replaces_is_directed_newer_to_older(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'bronze']);
        $relation = TrophyRelation::link($list['trophies'][1], $list['trophies'][0], TrophyRelationKind::Replaces);

        $this->assertSame($list['trophies'][1]->id, $relation->from_trophy_id);
        $this->assertViolation(self::UNIQUE, fn () => TrophyRelation::link($list['trophies'][0], $list['trophies'][1], TrophyRelationKind::Replaces));
    }

    public function test_relations_disappear_with_their_trophy_set(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();
        TrophySetRelation::link($a['set'], $b['set'], TrophySetRelationKind::Related);
        TrophyRelation::link($a['trophies'][0], $b['trophies'][0], TrophyRelationKind::Similar);

        $a['set']->delete();

        $this->assertSame(0, TrophySetRelation::query()->count());
        $this->assertSame(0, TrophyRelation::query()->count());
    }
}
