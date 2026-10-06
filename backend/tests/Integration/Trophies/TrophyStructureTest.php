<?php

namespace Tests\Integration\Trophies;

use App\Enums\TrophyGroupClassification;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophySet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * Acceptance scenarios A-E of M0.3 (docs/data-model/scenarios.md).
 */
class TrophyStructureTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    public function test_scenario_a_one_game_with_separate_ps4_and_ps5_releases_and_trophy_sets(): void
    {
        $game = Game::factory()->create();
        $ps4Release = GameRelease::factory()->for($game)->onPlatforms(['ps4'])->create();
        $ps5Release = GameRelease::factory()->for($game)->onPlatforms(['ps5'])->create();
        $ps4Set = $this->trophyList($game, platforms: ['ps4'])['set'];
        $ps5Set = $this->trophyList($game, platforms: ['ps5'])['set'];

        $ps4Release->useTrophySet($ps4Set);
        $ps5Release->useTrophySet($ps5Set);

        $this->assertCount(2, $game->trophySets);
        $this->assertSame([$ps4Set->id], $ps4Release->trophySets->pluck('id')->all());
        $this->assertSame([$ps5Set->id], $ps5Release->trophySets->pluck('id')->all());
        $this->assertSame(['ps4'], $ps4Set->platforms->pluck('code')->all());
        // Two sets, two distinct trophy records even if the lists look identical.
        $this->assertNotSame($ps4Set->trophies->first()?->id, $ps5Set->trophies->first()?->id);
    }

    public function test_scenario_b_one_trophy_set_with_several_platforms_shared_by_several_releases(): void
    {
        $game = Game::factory()->create();
        $set = TrophySet::factory()->for($game)->onPlatforms(['ps4', 'vita'])->create();
        $ps4 = GameRelease::factory()->for($game)->onPlatforms(['ps4'])->create();
        $vita = GameRelease::factory()->for($game)->onPlatforms(['vita'])->create();
        $ps4->useTrophySet($set);
        $vita->useTrophySet($set);

        $this->assertEqualsCanonicalizing(['ps4', 'vita'], $set->platforms->pluck('code')->all());
        $this->assertEqualsCanonicalizing([$ps4->id, $vita->id], $set->releases->pluck('id')->all());
    }

    public function test_scenario_c_regional_stacks_of_one_game(): void
    {
        $game = Game::factory()->create();
        $sets = [];
        foreach (['na', 'eu', 'jp'] as $region) {
            $release = GameRelease::factory()->for($game)->forRegion($region)->onPlatforms(['ps3'])->create();
            $sets[$region] = $this->trophyList($game, ['bronze', 'platinum'], ['ps3'])['set'];
            $release->useTrophySet($sets[$region]);
        }

        $this->assertCount(3, $game->trophySets);
        $this->assertSame(3, DB::table('game_release_trophy_sets')->where('game_id', $game->id)->count());
    }

    public function test_scenario_d_a_game_without_platinum_is_valid(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'silver', 'gold']);

        $this->assertFalse($list['set']->hasPlatinum());
        $this->assertSame(3, $list['set']->trophies()->count());
    }

    public function test_a_trophy_set_has_at_most_one_platinum(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'platinum']);

        $this->assertTrue($list['set']->hasPlatinum());
        $this->assertViolation(self::UNIQUE, fn () => Trophy::factory()->platinum()->create([
            'trophy_set_id' => $list['set']->id, 'trophy_group_id' => $list['group']->id, 'set_key' => 99,
        ]));
        // Another set may have its own platinum.
        $this->trophyList(grades: ['platinum']);
        $this->assertSame(2, Trophy::query()->where('grade', 'platinum')->count());
    }

    public function test_scenario_e_base_group_and_several_addon_groups(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'platinum']);
        $list['group']->update(['classification' => TrophyGroupClassification::Base, 'classification_origin' => 'manual']);
        $dlc1 = TrophyGroup::factory()->addon('001', 1, 'Add-on 1')->create(['trophy_set_id' => $list['set']->id]);
        $dlc2 = TrophyGroup::factory()->addon('002', 2, 'Add-on 2')->classified('expansion')->create(['trophy_set_id' => $list['set']->id]);
        $this->addTrophies($list['set'], $dlc1, ['gold', 'gold'], 10);
        $this->addTrophies($list['set'], $dlc2, ['silver'], 20);

        $groups = $list['set']->groups;
        $this->assertSame(['default', '001', '002'], $groups->pluck('set_key')->all());
        // Provider structure and TrophyVault classification are separate; 'unknown' is a real state.
        $this->assertSame(
            [TrophyGroupClassification::Base, TrophyGroupClassification::Unknown, TrophyGroupClassification::Expansion],
            $groups->pluck('classification')->all(),
        );
        $this->assertSame(['unknown'], DB::table('trophy_groups')->where('set_key', '001')->pluck('classification')->all());
        $this->assertSame(5, $list['set']->trophies()->count());
        $this->assertSame(2, $dlc1->trophies()->count());
    }

    public function test_group_keys_and_trophy_keys_are_unique_within_a_set_only(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();

        // Same keys in different sets are fine ...
        $this->assertSame($a['group']->set_key, $b['group']->set_key);
        $this->assertSame($a['trophies'][0]->set_key, $b['trophies'][0]->set_key);
        // ... but not inside one set.
        $this->assertViolation(self::UNIQUE, fn () => TrophyGroup::factory()->create(['trophy_set_id' => $a['set']->id, 'set_key' => 'default']));
        $this->assertViolation(self::UNIQUE, fn () => Trophy::factory()->create([
            'trophy_set_id' => $a['set']->id, 'trophy_group_id' => $a['group']->id, 'set_key' => 0,
        ]));
    }

    public function test_a_trophy_cannot_be_placed_in_a_group_of_another_set(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();

        $this->assertViolation(self::FOREIGN_KEY, fn () => Trophy::factory()->create([
            'trophy_set_id' => $a['set']->id, 'trophy_group_id' => $b['group']->id, 'set_key' => 50,
        ]));
    }

    public function test_grade_classification_and_ordinals_are_validated(): void
    {
        $list = $this->trophyList();
        // Raw inserts: the PHP enum casts would already refuse these values before the database sees them.
        $trophy = fn (array $over) => DB::table('trophies')->insert($over + [
            'trophy_set_id' => $list['set']->id, 'trophy_group_id' => $list['group']->id, 'set_key' => 77,
            'grade' => 'bronze', 'name' => 'x', 'ordinal' => 1,
        ]);
        $group = fn (array $over) => DB::table('trophy_groups')->insert($over + [
            'trophy_set_id' => $list['set']->id, 'set_key' => 'x', 'name' => 'x', 'ordinal' => 1,
        ]);

        $this->assertViolation(self::CHECK, fn () => $trophy(['grade' => 'diamond']));
        $this->assertViolation(self::CHECK, fn () => $trophy(['set_key' => -1]));
        $this->assertViolation(self::CHECK, fn () => $trophy(['ordinal' => -1]));
        $this->assertViolation(self::CHECK, fn () => $trophy(['name' => '']));
        $this->assertViolation(self::CHECK, fn () => $group(['classification' => 'dlc2']));
        $this->assertViolation(self::CHECK, fn () => $group(['classification_origin' => 'robot']));
    }

    public function test_hidden_trophies_and_metadata_round_trip(): void
    {
        $list = $this->trophyList();
        $hidden = Trophy::factory()->hidden()->create([
            'trophy_set_id' => $list['set']->id, 'trophy_group_id' => $list['group']->id, 'set_key' => 40,
            'metadata' => ['icon' => 'x.png'],
        ]);

        $fresh = $hidden->fresh();
        $this->assertTrue($fresh?->is_hidden);
        $this->assertSame(['icon' => 'x.png'], $fresh->metadata);
    }

    public function test_a_release_can_only_use_trophy_sets_of_its_own_game(): void
    {
        $release = GameRelease::factory()->create();
        $foreign = TrophySet::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => $release->useTrophySet($foreign));
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('game_release_trophy_sets')->insert([
            'game_release_id' => $release->id, 'trophy_set_id' => $foreign->id, 'game_id' => $foreign->game_id,
        ]));
    }

    public function test_a_game_with_trophy_sets_cannot_be_deleted_but_a_set_deletion_removes_its_structure(): void
    {
        $list = $this->trophyList();

        $this->assertViolation(self::RESTRICT, fn () => $list['set']->game?->delete());

        $list['set']->delete();
        $this->assertSame(0, TrophyGroup::query()->count());
        $this->assertSame(0, Trophy::query()->count());
    }

    public function test_trophy_set_platforms_must_exist(): void
    {
        $set = TrophySet::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => $set->platforms()->attach('n64'));
    }
}
