<?php

namespace Tests\Integration\Schema;

use App\Enums\GameRelationKind;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelation;
use App\Models\Catalog\GameRelease;
use App\Models\Catalog\Platform;
use App\Models\Catalog\Region;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Sync\Provider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** UUIDv7 everywhere (ADR 0007): valid, time-ordered, no auto-increment assumptions, interoperable PHP <-> PostgreSQL. */
class UuidTest extends TestCase
{
    use RefreshDatabase;

    private const UUID7 = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function test_laravel_generated_ids_are_valid_uuidv7(): void
    {
        foreach (Game::factory()->count(5)->create() as $game) {
            $this->assertMatchesRegularExpression(self::UUID7, (string) $game->id);
            $this->assertTrue(Str::isUuid((string) $game->id, 7));
        }
    }

    public function test_database_generated_ids_are_valid_uuidv7(): void
    {
        foreach (range(1, 5) as $i) {
            DB::table('games')->insert(['slug' => "db-{$i}", 'title' => "DB {$i}", 'sort_title' => "db {$i}"]);
        }

        foreach (DB::table('games')->pluck('id') as $id) {
            $this->assertIsString($id);
            $this->assertMatchesRegularExpression(self::UUID7, $id);
        }
    }

    public function test_database_ids_are_monotonic_within_a_session(): void
    {
        $ids = array_map(
            fn (object $row): string => (string) (((array) $row)['id'] ?? ''),
            DB::select('select uuidv7() as id from generate_series(1, 200)'),
        );
        $sorted = $ids;
        sort($sorted, SORT_STRING);

        $this->assertSame($sorted, $ids);
        $this->assertCount(200, array_unique($ids));
    }

    public function test_laravel_ids_sort_in_creation_order_across_milliseconds(): void
    {
        $ids = [];
        foreach (range(1, 15) as $i) {
            $ids[] = (new Game)->newUniqueId();
            usleep(2000);
        }
        $sorted = $ids;
        sort($sorted, SORT_STRING);

        $this->assertSame($sorted, $ids);
    }

    public function test_the_embedded_timestamp_is_recent_and_postgres_agrees_with_php_about_ordering(): void
    {
        $id = (new Game)->newUniqueId();
        $millis = hexdec(str_replace('-', '', substr($id, 0, 13)));

        $this->assertEqualsWithDelta(microtime(true) * 1000, $millis, 5000);
        // PostgreSQL's byte order on uuid equals PHP's string order on lower-case hex.
        $a = (string) Str::uuid7();
        usleep(2000);
        $b = (string) Str::uuid7();
        $this->assertSame(strcmp($a, $b) < 0, (bool) DB::scalar('select ?::uuid < ?::uuid', [$a, $b]));
    }

    public function test_uppercase_uuid_input_is_normalised_by_the_database_and_relation_ordering_stays_valid(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];
        // A caller holding upper-case ids (e.g. from an external system) must not break the ordered-pair rule.
        $a->id = strtoupper((string) $a->id);

        $relation = GameRelation::link($a, $b, GameRelationKind::Related);

        $this->assertLessThan(0, strcmp($relation->from_game_id, $relation->to_game_id));
        $this->assertSame(strtolower($relation->from_game_id), $relation->from_game_id);
    }

    public function test_models_do_not_expect_auto_increment_and_serialise_ids_as_strings(): void
    {
        $game = Game::factory()->create();

        $this->assertFalse($game->getIncrementing());
        $this->assertSame('string', $game->getKeyType());
        $this->assertSame($game->id, $game->toArray()['id'] ?? null);
        $decoded = json_decode((string) json_encode($game), true);
        $this->assertIsArray($decoded);
        $this->assertSame($game->id, $decoded['id'] ?? null);
        $key = $game->getKey();
        $this->assertIsString($key);
        $this->assertSame($game->id, $key);
    }

    public function test_route_binding_resolves_valid_ids_and_ignores_malformed_ones_without_a_database_error(): void
    {
        $game = Game::factory()->create();

        $this->assertTrue($game->is((new Game)->resolveRouteBinding((string) $game->id)));
        $this->assertNull((new Game)->resolveRouteBinding('00000000-0000-7000-8000-000000000000'));

        // Malformed ids never reach PostgreSQL (which would raise "invalid input syntax for type uuid"):
        // Laravel answers with ModelNotFoundException, i.e. a clean 404 in HTTP.
        foreach (['not-a-uuid', '12345', "' OR 1=1 --", ''] as $malformed) {
            try {
                (new Game)->resolveRouteBinding($malformed);
                $this->fail("'{$malformed}' was not rejected");
            } catch (ModelNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_natural_key_reference_models_and_composite_link_tables_do_not_depend_on_uuid_generation(): void
    {
        $platform = Platform::query()->findOrFail('ps5');
        $this->assertSame('ps5', $platform->getKey());
        $this->assertFalse($platform->getIncrementing());

        $release = GameRelease::factory()->onPlatforms(['ps4', 'ps5'])->create();
        $this->assertSame(2, DB::table('game_release_platforms')->where('game_release_id', $release->id)->count());
    }

    public function test_every_model_with_a_uuid_key_uses_the_shared_trait(): void
    {
        foreach (glob(app_path('Models/*/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename(dirname($file)).'\\'.basename($file, '.php');
            if (! is_subclass_of($class, Model::class) || in_array($class, [Platform::class, Region::class, Provider::class], true)) {
                continue;
            }
            $this->assertContains(HasUuidPrimaryKey::class, class_uses_recursive($class), $class);
        }
    }
}
