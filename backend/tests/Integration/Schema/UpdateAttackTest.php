<?php

namespace Tests\Integration\Schema;

use App\Enums\GameRelationKind;
use App\Enums\TrophySetRelationKind;
use App\Models\Catalog\ExternalIdentifier;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelation;
use App\Models\Catalog\GameRelease;
use App\Models\Identity\PsnAccount;
use App\Models\Trophies\TrophySet;
use App\Models\Trophies\TrophySetRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * Second hardening round: rows that are valid at INSERT time are mutated into invalid states with UPDATE,
 * and cross-owner / cross-account mix-ups are attempted through every key.
 */
class UpdateAttackTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    public function test_an_identifier_cannot_be_extended_to_a_second_target_by_update(): void
    {
        $game = Game::factory()->create();
        $set = TrophySet::factory()->for($game)->create();
        $identifier = ExternalIdentifier::factory()->create(['game_id' => $game->id]);

        $this->assertViolation(self::CHECK, fn () => DB::table('external_identifiers')->where('id', $identifier->id)->update(['trophy_set_id' => $set->id]));
        $this->assertViolation(self::CHECK, fn () => DB::table('external_identifiers')->where('id', $identifier->id)->update(['game_id' => null]));
        // re-pointing to exactly one other target in one statement is fine
        DB::table('external_identifiers')->where('id', $identifier->id)->update(['game_id' => null, 'trophy_set_id' => $set->id]);
        $this->assertSame($set->id, ExternalIdentifier::query()->findOrFail($identifier->id)->trophy_set_id);
    }

    public function test_a_release_set_link_cannot_be_moved_to_another_game_by_update(): void
    {
        $release = GameRelease::factory()->create();
        $own = TrophySet::factory()->create(['game_id' => $release->game_id]);
        $release->useTrophySet($own);
        $otherGame = Game::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('game_release_trophy_sets')->where('game_release_id', $release->id)->update(['game_id' => $otherGame->id]));
        // swapping in a set of another game while keeping the game id is rejected as well
        $foreign = TrophySet::factory()->create();
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('game_release_trophy_sets')->where('game_release_id', $release->id)->update(['trophy_set_id' => $foreign->id]));
    }

    public function test_earned_rows_cannot_be_handed_to_another_account_or_filed_under_another_set_by_update(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();
        [$owner, $other] = [PsnAccount::factory()->create(), PsnAccount::factory()->create()];
        $owner->earn($a['trophies'][0], now());
        $other->holdTrophySet($b['set']);

        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('psn_account_trophies')->where('psn_account_id', $owner->id)->update(['psn_account_id' => $other->id]));
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('psn_account_trophies')->where('psn_account_id', $owner->id)->update(['trophy_set_id' => $b['set']->id]));
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('psn_account_trophies')->where('psn_account_id', $owner->id)->update(['trophy_id' => $b['trophies'][0]->id]));
    }

    public function test_relations_cannot_be_flipped_into_contradictions_by_update(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];
        $forward = GameRelation::link($a, $b, GameRelationKind::SequelTo);
        $related = GameRelation::link($a, $b, GameRelationKind::Related);

        // flipping a directed relation is fine on its own ...
        DB::table('game_relations')->where('id', $forward->id)->update(['from_game_id' => $b->id, 'to_game_id' => $a->id]);
        // ... but a second sequel_to in the opposite direction is the contradiction the unique pair index forbids
        $again = GameRelation::link($a, $b, GameRelationKind::RemakeOf);
        $this->assertViolation(self::UNIQUE, fn () => DB::table('game_relations')->where('id', $again->id)->update(['kind' => 'sequel_to']));
        // a symmetric row can never be stored reversed
        $this->assertViolation(self::CHECK, fn () => DB::table('game_relations')->where('id', $related->id)->update(['from_game_id' => $related->to_game_id, 'to_game_id' => $related->from_game_id]));
        // nor turned into a self relation
        $this->assertViolation(self::CHECK, fn () => DB::table('game_relations')->where('id', $related->id)->update(['to_game_id' => $related->from_game_id]));
    }

    public function test_a_directed_trophy_set_relation_cannot_become_both_autopop_and_partial(): void
    {
        [$s1, $s2] = [TrophySet::factory()->create(), TrophySet::factory()->create()];
        TrophySetRelation::link($s1, $s2, TrophySetRelationKind::Autopop);
        $partial = TrophySetRelation::link($s2, $s1, TrophySetRelationKind::Equivalent);

        $this->assertViolation(self::UNIQUE, fn () => DB::table('trophy_set_relations')->where('id', $partial->id)->update(['kind' => 'partial_autopop']));
        $this->assertViolation(self::UNIQUE, fn () => DB::table('trophy_set_relations')->where('id', $partial->id)->update(['kind' => 'autopop']));
    }

    public function test_enum_style_columns_cannot_be_corrupted_by_update(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();

        $this->assertViolation(self::CHECK, fn () => DB::table('trophies')->where('id', $list['trophies'][0]->id)->update(['grade' => 'Platinum']));
        $this->assertViolation(self::CHECK, fn () => DB::table('trophy_groups')->where('id', $list['group']->id)->update(['classification' => 'DLC']));
        $this->assertViolation(self::CHECK, fn () => DB::table('psn_accounts')->where('id', $account->id)->update(['status' => 'deleted']));
        $this->assertViolation(self::NOT_NULL, fn () => DB::table('games')->update(['sort_title' => null]));
    }

    public function test_the_unique_active_identifier_rule_survives_updates_of_value_and_namespace(): void
    {
        $game = Game::factory()->create();
        ExternalIdentifier::factory()->create(['namespace' => 'a', 'value' => '1', 'game_id' => $game->id]);
        $second = ExternalIdentifier::factory()->create(['namespace' => 'a', 'value' => '2', 'game_id' => $game->id]);

        $this->assertViolation(self::UNIQUE, fn () => $second->update(['value' => '1']));
        $second->update(['namespace' => 'b', 'value' => '1']);
        $this->assertSame(2, ExternalIdentifier::query()->active()->where('value', '<>', 'x')->count());
    }

    public function test_redis_state_of_the_suite_is_isolated_from_development_redis_databases(): void
    {
        $this->assertSame('14', config()->string('database.redis.default.database'));
        $this->assertSame('15', config()->string('database.redis.cache.database'));
        $this->assertNotSame('0', config()->string('database.redis.default.database'));
        $this->assertNotSame('1', config()->string('database.redis.cache.database'));

        Redis::connection()->set('safety:probe', '1');
        // The development DBs (0/1) are other logical databases: the probe is invisible there.
        $dev = new \Redis;
        $dev->connect(config()->string('database.redis.default.host'), (int) config()->string('database.redis.default.port'));
        $password = config('database.redis.default.password');
        if (is_string($password) && $password !== '') {
            $dev->auth($password);
        }
        $dev->select(0);
        $this->assertSame(0, $dev->exists('trophyvault-test-safety:probe'));
        $this->assertSame(0, $dev->exists('safety:probe'));
        Redis::connection()->del('safety:probe');
    }
}
