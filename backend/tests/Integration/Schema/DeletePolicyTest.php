<?php

namespace Tests\Integration\Schema;

use App\Enums\TrophyRelationKind;
use App\Models\Catalog\Edition;
use App\Models\Catalog\ExternalIdentifier;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use App\Models\Identity\PsnAccount;
use App\Models\Identity\User;
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
 * The documented delete policy (docs/data-model/indexes-and-constraints.md) as executable facts:
 * canonical data cannot be deleted by accident, personal data follows its owner.
 */
class DeletePolicyTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    /** @return array{game: Game, release: GameRelease, list: array{set: TrophySet, group: TrophyGroup, trophies: list<Trophy>}} */
    private function fullGame(): array
    {
        $game = Game::factory()->create();
        $release = GameRelease::factory()->for($game)->onPlatforms(['ps4'])->create();
        $list = $this->trophyList($game);
        $release->useTrophySet($list['set']);
        ExternalIdentifier::factory()->create(['game_id' => null, 'trophy_set_id' => $list['set']->id]);

        return ['game' => $game, 'release' => $release, 'list' => $list];
    }

    public function test_a_game_cannot_be_deleted_while_anything_hangs_on_it(): void
    {
        $f = $this->fullGame();

        $this->assertViolation(self::RESTRICT, fn () => $f['game']->delete());
        $this->assertSame(1, Game::query()->whereKey($f['game']->id)->count());
    }

    public function test_a_release_cannot_be_deleted_into_its_game_but_its_links_follow_it(): void
    {
        $f = $this->fullGame();
        $f['release']->delete();

        $this->assertSame(0, DB::table('game_release_trophy_sets')->count());
        $this->assertSame(0, DB::table('game_release_platforms')->count());
        $this->assertSame(1, TrophySet::query()->count(), 'the trophy set survives its release');
    }

    public function test_deleting_a_trophy_set_without_progress_removes_exactly_its_own_subtree(): void
    {
        $f = $this->fullGame();
        $other = $this->trophyList();
        TrophyRelation::link($f['list']['trophies'][0], $other['trophies'][0], TrophyRelationKind::Equivalent);

        $f['list']['set']->delete();

        $this->assertSame(0, TrophyGroup::query()->where('trophy_set_id', $f['list']['set']->id)->count());
        $this->assertSame(0, Trophy::query()->where('trophy_set_id', $f['list']['set']->id)->count());
        $this->assertSame(0, DB::table('external_identifiers')->count());
        $this->assertSame(0, DB::table('trophy_relations')->count());
        $this->assertSame(0, DB::table('game_release_trophy_sets')->count());
        // untouched: the other set and the game with its release
        $this->assertSame(count($other['trophies']), Trophy::query()->where('trophy_set_id', $other['set']->id)->count());
        $this->assertSame(1, GameRelease::query()->count());
        $this->assertSame(2, Game::query()->count());
    }

    public function test_progress_protects_trophy_sets_groups_and_trophies_from_deletion(): void
    {
        $f = $this->fullGame();
        $account = PsnAccount::factory()->create();
        $account->earn($f['list']['trophies'][0], now());

        $this->assertViolation(self::RESTRICT, fn () => $f['list']['set']->delete());
        $this->assertViolation(self::RESTRICT, fn () => $f['list']['group']->delete());
        $this->assertViolation(self::RESTRICT, fn () => $f['list']['trophies'][0]->delete());
        // an unearned trophy of the same set can still be deleted
        $f['list']['trophies'][1]->delete();
        $this->assertSame(3, $f['list']['set']->trophies()->count());
    }

    public function test_deleting_a_user_removes_accounts_and_all_progress_but_no_canonical_data(): void
    {
        $f = $this->fullGame();
        $user = User::factory()->create();
        $a = PsnAccount::factory()->for($user)->create();
        $b = PsnAccount::factory()->for($user)->create();
        foreach ($f['list']['trophies'] as $trophy) {
            $a->earn($trophy, now());
        }
        $b->earn($f['list']['trophies'][0], now());
        $bystander = PsnAccount::factory()->create();
        $bystander->earn($f['list']['trophies'][1], now());

        $user->delete();

        $this->assertSame(0, PsnAccount::query()->whereIn('id', [$a->id, $b->id])->count());
        $this->assertSame(1, DB::table('psn_account_trophies')->count(), 'only the bystander account keeps its progress');
        $this->assertSame(1, DB::table('psn_account_trophy_sets')->count());
        $this->assertSame(4, Trophy::query()->count());
        $this->assertSame(1, Game::query()->count());
    }

    public function test_deleting_a_psn_account_leaves_the_users_other_accounts_alone(): void
    {
        $f = $this->fullGame();
        $user = User::factory()->create();
        [$a, $b] = [PsnAccount::factory()->for($user)->create(), PsnAccount::factory()->for($user)->create()];
        $a->earn($f['list']['trophies'][0], now());
        $b->earn($f['list']['trophies'][0], now());

        $a->delete();

        $this->assertSame(1, $user->psnAccounts()->count());
        $this->assertSame([$b->id], DB::table('psn_account_trophies')->pluck('psn_account_id')->all());
    }

    public function test_reference_rows_cannot_be_deleted_while_used(): void
    {
        GameRelease::factory()->forRegion('eu')->onPlatforms(['ps5'])->create();
        TrophySet::factory()->onPlatforms(['vita'])->create();

        $this->assertViolation(self::RESTRICT, fn () => DB::table('platforms')->where('code', 'ps5')->delete());
        $this->assertViolation(self::RESTRICT, fn () => DB::table('platforms')->where('code', 'vita')->delete());
        $this->assertViolation(self::RESTRICT, fn () => DB::table('regions')->where('code', 'eu')->delete());
        // unused reference rows may be deleted by a (deliberate) migration; the database does not forbid it
        $this->assertSame(1, DB::table('platforms')->where('code', 'psp')->delete());
    }

    public function test_editions_with_releases_cannot_be_deleted(): void
    {
        $release = GameRelease::factory()->forEdition(Edition::factory()->create())->create();

        $this->assertViolation(self::RESTRICT, fn () => $release->edition?->delete());
    }

    public function test_moving_owned_rows_between_owners_is_blocked_by_the_composite_keys(): void
    {
        $f = $this->fullGame();
        $otherGame = Game::factory()->create();
        $other = $this->trophyList();

        // a trophy set cannot be re-parented while releases link it under the old game
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('trophy_sets')->where('id', $f['list']['set']->id)->update(['game_id' => $otherGame->id]));
        // a trophy cannot be moved to a group of another set
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('trophies')->where('id', $f['list']['trophies'][0]->id)->update(['trophy_group_id' => $other['group']->id]));
        // nor to another set while keeping its group
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('trophies')->where('id', $f['list']['trophies'][0]->id)->update(['trophy_set_id' => $other['set']->id, 'set_key' => 999]));
        // a release cannot be moved to another game while it is linked to a trophy set
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('game_releases')->where('id', $f['release']->id)->update(['game_id' => $otherGame->id]));
    }
}
