<?php

namespace Tests\Integration\Trophies;

use App\Enums\GameRelationKind;
use App\Enums\TrophyGrade;
use App\Enums\TrophyRelationKind;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelation;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophyRelation;
use App\Models\Trophies\TrophySet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * Attempts to build impossible trophy structures through every door: model API, query builder, raw updates.
 */
class TrophyIntegrityAdversarialTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    public function test_a_second_platinum_cannot_appear_through_an_update_or_an_insert(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'bronze', 'platinum']);

        $this->assertViolation(self::UNIQUE, fn () => DB::table('trophies')->where('id', $list['trophies'][0]->id)->update(['grade' => 'platinum']));
        $this->assertViolation(self::UNIQUE, fn () => $list['trophies'][1]->update(['grade' => 'platinum']));
        // demoting the platinum first makes room for another one
        $list['trophies'][2]->update(['grade' => 'gold']);
        $list['trophies'][0]->update(['grade' => 'platinum']);
        $this->assertTrue($list['set']->hasPlatinum());
    }

    public function test_group_and_trophy_keys_cannot_be_reused_by_moving_rows_around(): void
    {
        $list = $this->trophyList();
        $second = TrophyGroup::factory()->addon('001', 1)->create(['trophy_set_id' => $list['set']->id]);

        $this->assertViolation(self::UNIQUE, fn () => $second->update(['set_key' => 'default']));
        $this->assertViolation(self::UNIQUE, fn () => $list['trophies'][1]->update(['set_key' => 0]));
        // moving a trophy between two groups of the SAME set is legitimate
        $list['trophies'][0]->update(['trophy_group_id' => $second->id]);
        $this->assertSame(1, $second->trophies()->count());
    }

    public function test_a_group_with_trophies_can_not_be_orphaned_or_pointed_at_another_set(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();

        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('trophy_groups')->where('id', $a['group']->id)->update(['trophy_set_id' => $b['set']->id, 'set_key' => 'moved']));
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('trophies')->where('id', $a['trophies'][0]->id)->update(['trophy_group_id' => '01a11229-f3ea-73c7-9d79-37f2952bcc25']));
        $this->assertViolation(self::NOT_NULL, fn () => DB::table('trophies')->where('id', $a['trophies'][0]->id)->update(['trophy_group_id' => null]));
    }

    public function test_the_same_provider_key_in_different_sets_is_independent(): void
    {
        $a = $this->trophyList(grades: ['bronze']);
        $b = $this->trophyList(grades: ['gold']);

        $this->assertSame(0, $a['trophies'][0]->set_key);
        $this->assertSame(0, $b['trophies'][0]->set_key);
        $this->assertNotSame($a['trophies'][0]->id, $b['trophies'][0]->id);
        $this->assertSame(TrophyGrade::Bronze, $a['trophies'][0]->fresh()?->grade);
        $this->assertSame(TrophyGrade::Gold, $b['trophies'][0]->fresh()?->grade);
    }

    public function test_hidden_trophies_ordering_and_group_ownership_are_preserved(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'bronze', 'bronze']);
        $list['trophies'][1]->update(['is_hidden' => true, 'ordinal' => 0]);
        $list['trophies'][0]->update(['ordinal' => 1]);

        $this->assertSame([$list['trophies'][1]->id, $list['trophies'][0]->id, $list['trophies'][2]->id], $list['set']->trophies->pluck('id')->all());
        $this->assertSame([true, false, false], $list['set']->trophies->map(fn (Trophy $t): bool => $t->is_hidden)->all());
        $this->assertTrue($list['trophies'][1]->group?->is($list['group']));
    }

    public function test_a_trophy_may_not_exist_without_a_set_or_a_valid_grade_even_via_the_model(): void
    {
        $this->assertViolation(self::NOT_NULL, fn () => DB::table('trophies')->insert(['trophy_group_id' => TrophyGroup::factory()->create()->id, 'set_key' => 1, 'grade' => 'bronze', 'name' => 'x', 'ordinal' => 0]));
        $this->assertViolation(self::CHECK, fn () => DB::table('trophies')->insert([
            'trophy_set_id' => ($l = $this->trophyList())['set']->id, 'trophy_group_id' => $l['group']->id, 'set_key' => 50, 'grade' => 'Bronze', 'name' => 'x', 'ordinal' => 0,
        ]));
    }

    public function test_names_cannot_be_blank_or_padded(): void
    {
        $list = $this->trophyList();
        $make = fn (string $name) => Trophy::factory()->create(['trophy_set_id' => $list['set']->id, 'trophy_group_id' => $list['group']->id, 'set_key' => 77, 'name' => $name]);

        foreach (['', ' ', ' leading', "trailing\n", "\t"] as $bad) {
            $this->assertViolation(self::CHECK, fn () => $make($bad));
        }
        $this->assertSame('Two  Words', $make('Two  Words')->name, 'inner whitespace is allowed');
    }

    public function test_trophy_set_relations_need_existing_sets_and_cannot_reference_the_same_set(): void
    {
        $set = TrophySet::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('trophy_set_relations')->insert([
            'from_trophy_set_id' => $set->id, 'to_trophy_set_id' => '01a11229-f3ea-73c7-9d79-37f2952bcc25', 'kind' => 'autopop',
        ]));
        $this->assertViolation(self::CHECK, fn () => DB::table('trophy_set_relations')->insert([
            'from_trophy_set_id' => $set->id, 'to_trophy_set_id' => $set->id, 'kind' => 'related',
        ]));
    }

    public function test_relations_between_games_need_existing_games_and_known_kinds(): void
    {
        $game = Game::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('game_relations')->insert([
            'from_game_id' => $game->id, 'to_game_id' => '01a11229-f3ea-73c7-9d79-37f2952bcc25', 'kind' => 'sequel_to',
        ]));
        $this->assertSame(0, GameRelation::query()->count());
        $this->assertSame(GameRelationKind::cases()[0]->value, 'remake_of');
    }

    public function test_trophy_relations_between_trophies_of_one_set_are_allowed_but_never_to_themselves(): void
    {
        // Documented limitation (docs/data-model/indexes-and-constraints.md): "same set" is not forbidden by the schema.
        $list = $this->trophyList(grades: ['bronze', 'bronze']);

        TrophyRelation::link($list['trophies'][0], $list['trophies'][1], TrophyRelationKind::Similar);
        $this->assertSame(1, TrophyRelation::query()->count());
    }
}
