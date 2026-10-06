<?php

namespace Tests\Integration\Catalog;

use App\Models\Catalog\Edition;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use AssertsDatabaseViolations;
    use RefreshDatabase;

    public function test_a_game_is_independent_of_platform_and_release(): void
    {
        $game = Game::factory()->create(['title' => 'Grand Theft Auto V']);

        $this->assertTrue(Game::query()->whereKey($game->id)->exists());
        $this->assertCount(0, $game->releases);
        $this->assertCount(0, $game->trophySets);
    }

    public function test_slug_must_be_unique_and_well_formed(): void
    {
        Game::factory()->create(['slug' => 'gta-v']);

        $this->assertViolation(self::UNIQUE, fn () => Game::factory()->create(['slug' => 'gta-v']));
        $this->assertViolation(self::CHECK, fn () => Game::factory()->create(['slug' => 'Not A Slug']));
        $this->assertViolation(self::CHECK, fn () => Game::factory()->create(['slug' => 'trailing-']));
    }

    public function test_titles_must_not_be_empty(): void
    {
        $this->assertViolation(self::CHECK, fn () => Game::factory()->create(['title' => '']));
        $this->assertViolation(self::CHECK, fn () => Game::factory()->create(['sort_title' => '']));
    }

    public function test_games_are_retired_not_deleted(): void
    {
        $game = Game::factory()->retired()->create();

        $this->assertTrue($game->isRetired());
        $this->assertInstanceOf(CarbonImmutable::class, $game->retired_at);
    }

    public function test_title_search_matches_substrings_case_insensitively(): void
    {
        Game::factory()->create(['title' => 'Red Dead Redemption 2']);
        Game::factory()->create(['title' => 'Grand Theft Auto V']);

        $found = Game::query()->whereRaw('lower(title) like ?', ['%dead red%'])->orWhereRaw('lower(title) like ?', ['%dead%'])->pluck('title');

        $this->assertSame(['Red Dead Redemption 2'], $found->all());
    }

    public function test_a_release_may_span_several_platforms_and_a_platform_has_many_releases(): void
    {
        $game = Game::factory()->create();
        $crossGen = GameRelease::factory()->for($game)->onPlatforms(['ps4', 'ps5'])->create();
        $ps4Only = GameRelease::factory()->for($game)->onPlatforms(['ps4'])->create();

        $this->assertEqualsCanonicalizing(['ps4', 'ps5'], $crossGen->platforms->pluck('code')->all());
        $this->assertSame(2, DB::table('game_release_platforms')->where('platform_code', 'ps4')->count());
        $this->assertEqualsCanonicalizing(
            [$ps4Only->id, $crossGen->id],
            DB::table('game_release_platforms')->where('platform_code', 'ps4')->pluck('game_release_id')->all(),
        );
    }

    public function test_release_platform_and_region_must_exist(): void
    {
        $release = GameRelease::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => $release->platforms()->attach('dreamcast'));
        $this->assertViolation(self::FOREIGN_KEY, fn () => GameRelease::factory()->forRegion('mars')->create());
    }

    public function test_release_region_defaults_to_unknown_and_global_is_a_real_value(): void
    {
        $unknown = GameRelease::factory()->create();
        $global = GameRelease::factory()->forRegion('global')->create();

        $this->assertSame('unknown', $unknown->fresh()?->region_code);
        $this->assertSame('global', $global->region?->code);
    }

    public function test_the_same_platform_cannot_be_attached_twice(): void
    {
        $release = GameRelease::factory()->create();
        $release->platforms()->attach('ps5');

        $this->assertViolation(self::UNIQUE, fn () => $release->platforms()->attach('ps5'));
    }

    public function test_an_edition_belongs_to_one_game_and_does_not_create_a_new_game(): void
    {
        $game = Game::factory()->create();
        $deluxe = Edition::factory()->for($game)->create(['slug' => 'deluxe', 'name' => 'Deluxe']);
        $release = GameRelease::factory()->for($game)->create(['edition_id' => $deluxe->id]);

        $this->assertSame($game->id, $release->edition?->game_id);
        $this->assertSame(1, Game::query()->count());
        $this->assertViolation(self::UNIQUE, fn () => Edition::factory()->for($game)->create(['slug' => 'deluxe']));
    }

    public function test_a_release_cannot_use_an_edition_of_another_game(): void
    {
        $edition = Edition::factory()->create();
        $other = Game::factory()->create();

        $this->assertViolation(self::FOREIGN_KEY, fn () => GameRelease::factory()->for($other)->create(['edition_id' => $edition->id]));
    }

    public function test_a_game_with_releases_cannot_be_deleted(): void
    {
        $release = GameRelease::factory()->create();

        $this->assertViolation(self::RESTRICT, fn () => $release->game?->delete());
    }

    public function test_deleting_a_release_removes_its_platform_rows(): void
    {
        $release = GameRelease::factory()->onPlatforms(['ps3'])->create();
        $release->delete();

        $this->assertSame(0, DB::table('game_release_platforms')->count());
    }
}
