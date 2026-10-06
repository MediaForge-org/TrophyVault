<?php

namespace Tests\Integration\Trophies;

use App\Enums\TrophyGroupClassification;
use App\Models\Identity\PsnAccount;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophySet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * Completion is never stored (ADR 0012). These tests prove the schema holds everything a future completion engine
 * needs: per-set, per-group and platinum facts are derivable with plain SQL from definitions + earned rows.
 * (The queries below are test helpers, not the completion engine.)
 */
class CompletionDerivationTest extends TestCase
{
    use BuildsTrophyLists;
    use RefreshDatabase;

    /** @return array{total: int, earned: int, platinum_exists: bool, platinum_earned: bool} */
    private function setFacts(PsnAccount $account, TrophySet $set): array
    {
        $total = (int) DB::table('trophies')->where('trophy_set_id', $set->id)->count();
        $earned = (int) DB::table('psn_account_trophies')->where('psn_account_id', $account->id)->where('trophy_set_id', $set->id)->count();
        $platinumId = DB::table('trophies')->where('trophy_set_id', $set->id)->where('grade', 'platinum')->value('id');

        return [
            'total' => $total,
            'earned' => $earned,
            'platinum_exists' => $platinumId !== null,
            'platinum_earned' => $platinumId !== null && DB::table('psn_account_trophies')->where('psn_account_id', $account->id)->where('trophy_id', $platinumId)->exists(),
        ];
    }

    /**
     * @return array<string, array{total: int, earned: int, classification: string}> keyed by group set_key
     */
    private function groupFacts(PsnAccount $account, TrophySet $set): array
    {
        $rows = DB::table('trophy_groups as g')
            ->join('trophies as t', 't.trophy_group_id', '=', 'g.id')
            ->leftJoin('psn_account_trophies as e', fn ($join) => $join->on('e.trophy_id', '=', 't.id')->where('e.psn_account_id', '=', $account->id))
            ->where('g.trophy_set_id', $set->id)
            ->groupBy('g.set_key', 'g.classification')
            ->orderBy('g.set_key')
            ->selectRaw('g.set_key, g.classification, count(t.id) as total, count(e.trophy_id) as earned')
            ->get();

        $facts = [];
        foreach ($rows as $row) {
            $facts[(string) $row->set_key] = ['total' => (int) $row->total, 'earned' => (int) $row->earned, 'classification' => (string) $row->classification];
        }

        return $facts;
    }

    public function test_zero_of_n_trophies(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'silver', 'platinum']);
        $account = PsnAccount::factory()->create();
        $account->holdTrophySet($list['set']);

        $this->assertSame(['total' => 3, 'earned' => 0, 'platinum_exists' => true, 'platinum_earned' => false], $this->setFacts($account, $list['set']));
    }

    public function test_partial_progress(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'silver', 'gold', 'platinum']);
        $account = PsnAccount::factory()->create();
        $account->earn($list['trophies'][0], now())->earn($list['trophies'][1], now());

        $facts = $this->setFacts($account, $list['set']);
        $this->assertSame(2, $facts['earned']);
        $this->assertSame(4, $facts['total']);
        $this->assertFalse($facts['platinum_earned']);
    }

    public function test_all_base_trophies_earned_but_the_set_is_not_complete(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'silver', 'platinum']);
        $list['group']->update(['classification' => TrophyGroupClassification::Base]);
        $dlc = TrophyGroup::factory()->addon('001', 1)->classified('dlc')->create(['trophy_set_id' => $list['set']->id]);
        $dlcTrophies = $this->addTrophies($list['set'], $dlc, ['gold', 'gold'], 10);
        $account = PsnAccount::factory()->create();
        foreach ($list['trophies'] as $trophy) {
            $account->earn($trophy, now());
        }

        $groups = $this->groupFacts($account, $list['set']);
        $this->assertSame(['total' => 3, 'earned' => 3, 'classification' => 'base'], $groups['default']);
        $this->assertSame(['total' => 2, 'earned' => 0, 'classification' => 'dlc'], $groups['001']);
        $this->assertTrue($this->setFacts($account, $list['set'])['platinum_earned'], 'platinum earned although DLC is open');
        $this->assertLessThan($this->setFacts($account, $list['set'])['total'], $this->setFacts($account, $list['set'])['earned']);

        // finishing the DLC completes the set
        foreach ($dlcTrophies as $trophy) {
            $account->earn($trophy, now());
        }
        $done = $this->setFacts($account, $list['set']);
        $this->assertSame($done['total'], $done['earned']);
    }

    public function test_a_game_without_platinum_is_complete_when_every_trophy_is_earned(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'silver', 'gold']);
        $account = PsnAccount::factory()->create();
        foreach ($list['trophies'] as $trophy) {
            $account->earn($trophy, now());
        }

        $facts = $this->setFacts($account, $list['set']);
        $this->assertFalse($facts['platinum_exists'], 'platinum status is not_applicable');
        $this->assertFalse($facts['platinum_earned']);
        $this->assertSame($facts['total'], $facts['earned']);
    }

    public function test_unclassified_groups_stay_unknown_and_are_not_assumed_to_be_base(): void
    {
        $list = $this->trophyList();
        TrophyGroup::factory()->addon('001', 1)->create(['trophy_set_id' => $list['set']->id]);
        $account = PsnAccount::factory()->create();

        $groups = $this->groupFacts($account, $list['set']);
        $this->assertSame('unknown', DB::table('trophy_groups')->where('set_key', 'default')->value('classification'));
        $this->assertArrayHasKey('default', $groups);
        $this->assertNotContains('base', array_column($groups, 'classification'));
    }

    public function test_empty_groups_do_not_distort_the_counts_and_multiple_groups_sum_up(): void
    {
        $list = $this->trophyList(grades: ['bronze', 'bronze']);
        TrophyGroup::factory()->addon('001', 1)->create(['trophy_set_id' => $list['set']->id]); // group without trophies
        $second = TrophyGroup::factory()->addon('002', 2)->create(['trophy_set_id' => $list['set']->id]);
        $this->addTrophies($list['set'], $second, ['gold'], 10);
        $account = PsnAccount::factory()->create();
        $account->earn($list['trophies'][0], now());

        $this->assertSame(3, $this->setFacts($account, $list['set'])['total']);
        $this->assertEqualsCanonicalizing(['default', '002'], array_keys($this->groupFacts($account, $list['set'])), 'a group without trophies has nothing to complete and is not reported');
    }

    public function test_equivalent_sets_of_one_game_are_derivable_per_account_independently(): void
    {
        $ps4 = $this->trophyList(platforms: ['ps4']);
        $ps5 = $this->trophyList(platforms: ['ps5']);
        $account = PsnAccount::factory()->create();
        foreach ($ps4['trophies'] as $trophy) {
            $account->earn($trophy, now());
        }
        $account->holdTrophySet($ps5['set']);

        $this->assertSame($this->setFacts($account, $ps4['set'])['total'], $this->setFacts($account, $ps4['set'])['earned']);
        $this->assertSame(0, $this->setFacts($account, $ps5['set'])['earned']);
        $this->assertSame(Trophy::query()->where('trophy_set_id', $ps5['set']->id)->count(), $this->setFacts($account, $ps5['set'])['total']);
    }
}
