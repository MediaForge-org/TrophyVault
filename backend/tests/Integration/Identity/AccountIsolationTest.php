<?php

namespace Tests\Integration\Identity;

use App\Models\Identity\PsnAccount;
use App\Models\Identity\User;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophySet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * ADR 0012: earned state belongs to exactly one PSN account; accounts, users and trophies never bleed into each other.
 */
class AccountIsolationTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    public function test_account_a_earning_a_trophy_does_not_affect_account_b(): void
    {
        $list = $this->trophyList();
        [$a, $b] = [PsnAccount::factory()->create(), PsnAccount::factory()->create()];
        $a->earn($list['trophies'][0], now());
        $b->holdTrophySet($list['set']);

        $this->assertSame(1, $a->earnedTrophies()->count());
        $this->assertSame(0, $b->earnedTrophies()->count());
        $this->assertSame([], $b->earnedTrophies()->pluck('trophies.id')->all());
        // both hold the list, only one earned something
        $this->assertSame(2, DB::table('psn_account_trophy_sets')->count());
        $this->assertSame(1, DB::table('psn_account_trophies')->count());
    }

    public function test_the_same_trophy_can_be_earned_by_two_accounts_at_different_times(): void
    {
        $list = $this->trophyList();
        [$a, $b] = [PsnAccount::factory()->create(), PsnAccount::factory()->create()];
        $a->earn($list['trophies'][0], now()->subDays(10));
        $b->earn($list['trophies'][0], now()->subDay());

        $times = DB::table('psn_account_trophies')->orderBy('earned_at')->pluck('psn_account_id')->all();
        $this->assertSame([$a->id, $b->id], $times);
    }

    public function test_two_local_users_with_their_own_accounts_stay_separate(): void
    {
        $list = $this->trophyList();
        [$alice, $bob] = [User::factory()->create(), User::factory()->create()];
        $aliceAccount = PsnAccount::factory()->for($alice)->create();
        $bobAccount = PsnAccount::factory()->for($bob)->create();
        $aliceAccount->earn($list['trophies'][0], now());
        $aliceAccount->earn($list['trophies'][1], now());
        $bobAccount->earn($list['trophies'][2], now());

        $earnedBy = fn (User $user): array => DB::table('psn_account_trophies')
            ->join('psn_accounts', 'psn_accounts.id', '=', 'psn_account_trophies.psn_account_id')
            ->where('psn_accounts.user_id', $user->id)
            ->pluck('psn_account_trophies.trophy_id')->all();

        $this->assertEqualsCanonicalizing([$list['trophies'][0]->id, $list['trophies'][1]->id], $earnedBy($alice));
        $this->assertSame([$list['trophies'][2]->id], $earnedBy($bob));
        $this->assertCount(1, $alice->psnAccounts);
        $this->assertSame($alice->id, $alice->psnAccounts->first()?->user?->id);
    }

    public function test_one_user_with_two_accounts_keeps_progress_per_account(): void
    {
        $list = $this->trophyList();
        $user = User::factory()->create();
        [$main, $alt] = [PsnAccount::factory()->for($user)->create(), PsnAccount::factory()->for($user)->create()];
        $main->earn($list['trophies'][0], now());
        $alt->earn($list['trophies'][1], now());

        $this->assertSame([$list['trophies'][0]->id], $main->earnedTrophies()->pluck('trophies.id')->all());
        $this->assertSame([$list['trophies'][1]->id], $alt->earnedTrophies()->pluck('trophies.id')->all());
    }

    public function test_earn_uses_the_trophys_own_set_and_adds_it_to_the_library_once(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();
        $account = PsnAccount::factory()->create();

        $account->earn($a['trophies'][0], now())->earn($a['trophies'][1], now())->earn($b['trophies'][0], now());

        $this->assertEqualsCanonicalizing([$a['set']->id, $b['set']->id], $account->trophySets()->pluck('trophy_sets.id')->all());
        $perSet = DB::table('psn_account_trophies')->selectRaw('trophy_set_id, count(*) c')->groupBy('trophy_set_id')->pluck('c', 'trophy_set_id')->all();
        $this->assertEquals([$a['set']->id => 2, $b['set']->id => 1], $perSet);
    }

    public function test_a_trophy_cannot_be_filed_under_another_set_even_via_the_raw_pivot_api(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $account->holdTrophySet($a['set'])->holdTrophySet($b['set']);

        // both sets are in the library, but trophy b0 is not part of set a
        $this->assertViolation(self::FOREIGN_KEY, fn () => $account->earnedTrophies()->attach($b['trophies'][0]->id, ['trophy_set_id' => $a['set']->id]));
    }

    public function test_the_account_set_row_belongs_to_the_account_that_earned(): void
    {
        $list = $this->trophyList();
        [$holder, $other] = [PsnAccount::factory()->create(), PsnAccount::factory()->create()];
        $holder->holdTrophySet($list['set']);

        // `other` does not hold the set, so it cannot have earned anything in it
        $this->assertViolation(self::FOREIGN_KEY, fn () => DB::table('psn_account_trophies')->insert([
            'psn_account_id' => $other->id, 'trophy_id' => $list['trophies'][0]->id, 'trophy_set_id' => $list['set']->id,
        ]));
    }

    public function test_the_progress_of_one_set_does_not_leak_into_another_sets_counts(): void
    {
        $a = $this->trophyList(grades: ['bronze', 'bronze']);
        $b = $this->trophyList(grades: ['bronze', 'bronze', 'bronze']);
        $account = PsnAccount::factory()->create();
        $account->earn($a['trophies'][0], now())->earn($b['trophies'][0], now())->earn($b['trophies'][1], now());

        $count = fn (TrophySet $set): int => DB::table('psn_account_trophies')->where('psn_account_id', $account->id)->where('trophy_set_id', $set->id)->count();
        $this->assertSame(1, $count($a['set']));
        $this->assertSame(2, $count($b['set']));
    }

    public function test_earn_rejects_a_trophy_without_a_set(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PsnAccount::factory()->create()->earn(new Trophy);
    }

    public function test_the_psn_account_id_is_globally_unique_and_whitespace_free(): void
    {
        $account = PsnAccount::factory()->create();

        $this->assertViolation(self::UNIQUE, fn () => PsnAccount::factory()->create(['psn_account_id' => $account->psn_account_id]));
        $this->assertViolation(self::CHECK, fn () => PsnAccount::factory()->create(['psn_account_id' => '123 456']));
        $this->assertViolation(self::CHECK, fn () => PsnAccount::factory()->create(['online_id' => ' padded']));
    }
}
