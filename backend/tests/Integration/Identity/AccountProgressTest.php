<?php

namespace Tests\Integration\Identity;

use App\Models\Identity\PsnAccount;
use App\Models\Identity\User;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophySet;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * Earned state belongs to the PSN account (ADR 0012). Scenarios F and H of M0.3.
 */
class AccountProgressTest extends TestCase
{
    use AssertsDatabaseViolations;
    use BuildsTrophyLists;
    use RefreshDatabase;

    /**
     * @param  list<Trophy>  $trophies
     */
    private function earn(PsnAccount $account, TrophySet $set, array $trophies): void
    {
        $account->trophySets()->syncWithoutDetaching([$set->id => ['first_seen_at' => now()]]);
        foreach ($trophies as $trophy) {
            $account->earnedTrophies()->attach($trophy->id, ['trophy_set_id' => $set->id, 'earned_at' => now()]);
        }
    }

    public function test_scenario_f_platinum_earned_but_set_not_complete_is_derived_not_stored(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'bronze', 'silver', 'platinum']);
        $account = PsnAccount::factory()->create();
        $this->earn($account, $list['set'], [$list['trophies'][0], $list['trophies'][3]]);

        $earned = DB::table('psn_account_trophies')->where('psn_account_id', $account->id)->where('trophy_set_id', $list['set']->id);
        $platinumEarned = (clone $earned)->where('trophy_id', $list['trophies'][3]->id)->exists();
        $total = $list['set']->trophies()->count();

        $this->assertTrue($platinumEarned);
        $this->assertSame(2, $earned->count());
        $this->assertLessThan($total, $earned->count(), 'Trophy set is not 100 % although the platinum is earned');
        // No completion/percentage column exists to go stale.
        $this->assertFalse(DB::getSchemaBuilder()->hasColumn('psn_account_trophy_sets', 'completed'));
    }

    public function test_scenario_h_two_psn_accounts_have_independent_progress_on_the_same_set(): void
    {
        $user = User::factory()->create();
        $list = $this->trophyList(grades: ['bronze', 'bronze', 'gold']);
        $main = PsnAccount::factory()->for($user)->create();
        $alt = PsnAccount::factory()->for($user)->create();

        $this->earn($main, $list['set'], $list['trophies']);
        $this->earn($alt, $list['set'], [$list['trophies'][0]]);

        $this->assertSame(3, $main->earnedTrophies()->count());
        $this->assertSame(1, $alt->earnedTrophies()->count());
        $this->assertCount(2, $user->psnAccounts);
        $this->assertSame(4, DB::table('psn_account_trophies')->count());
    }

    public function test_one_psn_account_cannot_be_linked_to_two_local_users(): void
    {
        $account = PsnAccount::factory()->create();

        $this->assertViolation(self::UNIQUE, fn () => PsnAccount::factory()->create(['psn_account_id' => $account->psn_account_id]));
    }

    public function test_an_earned_trophy_requires_the_set_to_be_in_the_accounts_library(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => $account->earnedTrophies()->attach($list['trophies'][0]->id, [
            'trophy_set_id' => $list['set']->id, 'earned_at' => now(),
        ]));
    }

    public function test_an_earned_trophy_must_belong_to_the_stated_set(): void
    {
        $a = $this->trophyList();
        $b = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $account->trophySets()->attach($a['set']->id, ['first_seen_at' => now()]);

        $this->assertViolation(self::FOREIGN_KEY, fn () => $account->earnedTrophies()->attach($b['trophies'][0]->id, [
            'trophy_set_id' => $a['set']->id, 'earned_at' => now(),
        ]));
    }

    public function test_a_trophy_can_be_earned_only_once_per_account(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $this->earn($account, $list['set'], [$list['trophies'][0]]);

        $this->assertViolation(self::UNIQUE, fn () => $account->earnedTrophies()->attach($list['trophies'][0]->id, [
            'trophy_set_id' => $list['set']->id, 'earned_at' => now(),
        ]));
    }

    public function test_earned_at_may_be_unknown_and_is_a_timestamptz_instant(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $account->trophySets()->attach($list['set']->id, ['first_seen_at' => now()]);
        $account->earnedTrophies()->attach($list['trophies'][0]->id, ['trophy_set_id' => $list['set']->id, 'earned_at' => null]);
        // The provider reports local time with an offset; we store the instant.
        $account->earnedTrophies()->attach($list['trophies'][1]->id, ['trophy_set_id' => $list['set']->id, 'earned_at' => '2026-03-01T12:00:00+02:00']);

        $this->assertNull(DB::table('psn_account_trophies')->where('trophy_id', $list['trophies'][0]->id)->value('earned_at'));
        $this->assertTrue(DB::table('psn_account_trophies')->where('trophy_id', $list['trophies'][0]->id)->exists());
        $this->assertSame('2026-03-01 10:00:00+00', DB::table('psn_account_trophies')->where('trophy_id', $list['trophies'][1]->id)->selectRaw("to_char(earned_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') || '+00' as t")->value('t'));
    }

    public function test_an_earned_trophy_blocks_deletion_of_the_trophy_definition(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $this->earn($account, $list['set'], [$list['trophies'][0]]);

        $this->assertViolation(self::RESTRICT, fn () => $list['trophies'][0]->delete());
        $this->assertViolation(self::RESTRICT, fn () => $list['set']->delete());
    }

    public function test_deleting_an_account_removes_its_progress_but_not_the_definitions(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $this->earn($account, $list['set'], $list['trophies']);

        $account->delete();

        $this->assertSame(0, DB::table('psn_account_trophies')->count());
        $this->assertSame(0, DB::table('psn_account_trophy_sets')->count());
        $this->assertSame(4, $list['set']->trophies()->count());
    }

    public function test_deleting_a_user_removes_the_linked_accounts(): void
    {
        $user = User::factory()->create();
        PsnAccount::factory()->for($user)->create();

        $user->delete();

        $this->assertSame(0, PsnAccount::query()->count());
    }

    public function test_provider_reported_progress_is_bounded_and_account_status_validated(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();

        $this->assertViolation(self::CHECK, fn () => $account->trophySets()->attach($list['set']->id, ['first_seen_at' => now(), 'provider_reported_progress' => 101]));
        $this->assertViolation(self::CHECK, fn () => DB::table('psn_accounts')->insert(['user_id' => $account->user_id, 'psn_account_id' => 'x1', 'online_id' => 'x', 'status' => 'banned']));
        $this->assertViolation(self::CHECK, fn () => PsnAccount::factory()->create(['online_id' => '']));
    }

    public function test_profile_is_a_json_object_and_user_emails_are_case_insensitively_unique(): void
    {
        $this->assertViolation(self::CHECK, fn () => PsnAccount::factory()->create(['profile' => '[]']));

        User::factory()->create(['email' => 'Player@Example.test']);
        $this->assertViolation(self::UNIQUE, fn () => User::factory()->create(['email' => 'player@example.test']));
    }

    public function test_timestamps_are_immutable_utc_instants(): void
    {
        $account = PsnAccount::factory()->create(['last_synced_at' => '2026-03-01T12:00:00+02:00']);
        $fresh = $account->fresh();

        $this->assertInstanceOf(CarbonImmutable::class, $fresh?->last_synced_at);
        $this->assertSame('2026-03-01T10:00:00+00:00', $fresh->last_synced_at->utc()->toIso8601String());
        $this->assertInstanceOf(CarbonImmutable::class, $fresh->created_at);
    }
}
