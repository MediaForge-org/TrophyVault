<?php

namespace Tests\Integration\Schema;

use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use App\Models\Identity\PsnAccount;
use App\Models\Trophies\Trophy;
use App\Support\Database\OffsetAwarePostgresGrammar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsTrophyLists;
use Tests\TestCase;

/**
 * Instants are timestamptz. Whatever zone PHP holds, the stored instant must be exact, comparisons must be exact,
 * and DB -> PHP -> DB must be stable (ADR 0008).
 */
class TimestampRoundTripTest extends TestCase
{
    use BuildsTrophyLists;
    use RefreshDatabase;

    private const INSTANT = '2026-07-01T10:00:00+00:00';

    /** @return array<string, array{string}> same instant (10:00:00 UTC) expressed in different zones */
    public static function sameInstantInDifferentZones(): array
    {
        return [
            'utc' => ['2026-07-01 10:00:00 UTC'],
            'positive offset' => ['2026-07-01 15:30:00 +05:30'],
            'negative offset' => ['2026-07-01 02:00:00 -08:00'],
            'extreme offset' => ['2026-07-02 00:00:00 +14:00'],
            'named zone with dst' => ['2026-07-01 06:00:00 America/New_York'],
            'named zone east' => ['2026-07-01 19:00:00 Asia/Tokyo'],
        ];
    }

    private function storedUtc(string $table, string $column, string $key, string $id, string $keyColumn = 'id'): string
    {
        $value = DB::scalar("select to_char({$column} at time zone 'UTC', 'YYYY-MM-DD\"T\"HH24:MI:SS') from {$table} where {$keyColumn} = ?", [$id]);
        $this->assertIsString($value);

        return $value.'+00:00';
    }

    public function test_the_connection_uses_the_offset_aware_grammar_and_a_utc_session(): void
    {
        $this->assertInstanceOf(OffsetAwarePostgresGrammar::class, DB::connection()->getQueryGrammar());
        $this->assertSame('Y-m-d H:i:sP', DB::connection()->getQueryGrammar()->getDateFormat());
        $this->assertSame('UTC', DB::scalar('show timezone'));
    }

    #[DataProvider('sameInstantInDifferentZones')]
    public function test_a_model_stores_the_exact_instant_whatever_the_carbon_zone(string $input): void
    {
        $game = Game::factory()->create(['retired_at' => CarbonImmutable::parse($input)]);
        $account = PsnAccount::factory()->create(['last_synced_at' => CarbonImmutable::parse($input)]);

        $this->assertSame(self::INSTANT, $this->storedUtc('games', 'retired_at', '', (string) $game->id));
        $this->assertSame(self::INSTANT, $this->storedUtc('psn_accounts', 'last_synced_at', '', (string) $account->id));
        $at = $game->fresh()?->getAttribute('retired_at');
        $this->assertInstanceOf(CarbonImmutable::class, $at);
        $this->assertSame(self::INSTANT, $at->utc()->toIso8601String());
    }

    #[DataProvider('sameInstantInDifferentZones')]
    public function test_the_query_builder_and_where_clauses_are_exact_too(string $input): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $account->holdTrophySet($list['set']);
        $at = CarbonImmutable::parse($input);

        // raw query-builder insert with a Carbon object (the Laravel default would drop the offset)
        DB::table('psn_account_trophies')->insert([
            'psn_account_id' => $account->id, 'trophy_id' => $list['trophies'][0]->id, 'trophy_set_id' => $list['set']->id, 'earned_at' => $at,
        ]);

        $this->assertSame(self::INSTANT, $this->storedUtc('psn_account_trophies', 'earned_at', '', (string) $list['trophies'][0]->id, 'trophy_id'));
        // comparisons: equal instant matches, one second earlier/later does not
        $query = fn (CarbonImmutable $when, string $op) => DB::table('psn_account_trophies')->where('earned_at', $op, $when)->count();
        $this->assertSame(1, $query($at, '='));
        $this->assertSame(0, $query($at->addSecond(), '='));
        $this->assertSame(1, $query($at->addSecond(), '<'));
        $this->assertSame(0, $query($at->subSecond(), '<'));
        $this->assertSame(1, $query($at->subSecond(), '>'));
    }

    public function test_earn_normalises_zones_and_is_idempotent_and_updates_the_time(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $trophy = $list['trophies'][0];

        $account->earn($trophy, CarbonImmutable::parse('2026-07-01 15:30:00 +05:30'));
        $account->earn($trophy, CarbonImmutable::parse('2026-07-01 15:30:00 +05:30'));
        $this->assertSame(1, DB::table('psn_account_trophies')->count());
        $this->assertSame(self::INSTANT, $this->storedUtc('psn_account_trophies', 'earned_at', '', (string) $trophy->id, 'trophy_id'));

        $account->earn($trophy, CarbonImmutable::parse('2026-07-02 00:00:00 +14:00')->addMinute());
        $this->assertSame('2026-07-01T10:01:00+00:00', $this->storedUtc('psn_account_trophies', 'earned_at', '', (string) $trophy->id, 'trophy_id'));

        $account->earn($trophy, null);
        $this->assertNull(DB::table('psn_account_trophies')->value('earned_at'));
        $this->assertSame(1, DB::table('psn_account_trophy_sets')->count());
    }

    public function test_sub_second_parts_are_truncated_not_rounded_up(): void
    {
        $game = Game::factory()->create(['retired_at' => CarbonImmutable::parse('2026-07-01 10:00:59.999999 UTC')]);

        $this->assertSame('2026-07-01T10:00:59+00:00', $this->storedUtc('games', 'retired_at', '', (string) $game->id));
    }

    public function test_db_php_db_round_trip_is_stable_and_unchanged_models_are_not_dirty(): void
    {
        $game = Game::factory()->create(['retired_at' => CarbonImmutable::parse('2026-07-01 15:30:00 +05:30')]);
        $before = $this->storedUtc('games', 'retired_at', '', (string) $game->id);

        $loaded = Game::query()->findOrFail($game->id);
        $this->assertFalse($loaded->isDirty());
        $loaded->retired_at = $loaded->retired_at;
        $loaded->save();
        $loaded->update(['retired_at' => Game::query()->findOrFail($game->id)->retired_at]);

        $this->assertSame($before, $this->storedUtc('games', 'retired_at', '', (string) $game->id));
    }

    public function test_instants_come_back_as_immutable_utc_values(): void
    {
        $game = Game::factory()->retired()->create();
        $fresh = Game::query()->findOrFail($game->id);

        foreach ([$fresh->created_at, $fresh->updated_at, $fresh->retired_at] as $instant) {
            $this->assertInstanceOf(CarbonImmutable::class, $instant);
            $this->assertSame(CarbonImmutable::class, $instant::class, 'No mutable Carbon instances');
            $this->assertSame(0, $instant->utcOffset());
        }
    }

    public function test_dates_without_time_do_not_shift_with_the_zone(): void
    {
        $release = GameRelease::factory()->create(['release_date' => CarbonImmutable::parse('2026-03-01 23:30:00 -08:00')]);

        $this->assertSame('2026-03-01', DB::table('game_releases')->where('id', $release->id)->value('release_date'));
        $date = $release->fresh()?->getAttribute('release_date');
        $this->assertInstanceOf(CarbonImmutable::class, $date);
        $this->assertSame('2026-03-01', $date->toDateString());
    }

    public function test_audit_timestamps_are_set_by_the_database_for_raw_inserts_and_by_the_app_for_models(): void
    {
        DB::table('games')->insert(['slug' => 'raw', 'title' => 'Raw', 'sort_title' => 'raw']);
        $raw = DB::table('games')->where('slug', 'raw')->first();
        $this->assertNotNull($raw);
        $this->assertNotNull($raw->created_at);

        $trophy = Trophy::factory()->create();
        $created = $trophy->created_at;
        $this->assertInstanceOf(CarbonImmutable::class, $created);
        $this->assertLessThan(5, abs(CarbonImmutable::now()->diffInSeconds($created)));
    }

    public function test_pivot_timestamps_use_the_same_exact_format(): void
    {
        $list = $this->trophyList();
        $account = PsnAccount::factory()->create();
        $account->holdTrophySet($list['set'], CarbonImmutable::parse('2026-07-01 15:30:00 +05:30'));

        $this->assertSame(self::INSTANT, $this->storedUtc('psn_account_trophy_sets', 'first_seen_at', '', (string) $account->id, 'psn_account_id'));
        $pivot = $account->trophySets()->firstOrFail()->getRelation('pivot');
        $this->assertInstanceOf(Pivot::class, $pivot);
        $this->assertInstanceOf(CarbonImmutable::class, $pivot->getAttribute('created_at'));
    }
}
