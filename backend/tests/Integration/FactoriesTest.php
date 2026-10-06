<?php

namespace Tests\Integration;

use App\Enums\GameRelationKind;
use App\Enums\PsnAccountStatus;
use App\Enums\TrophyGrade;
use App\Enums\TrophyRelationKind;
use App\Enums\TrophySetRelationKind;
use App\Models\Catalog\Edition;
use App\Models\Catalog\ExternalIdentifier;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelation;
use App\Models\Catalog\GameRelease;
use App\Models\Identity\PsnAccount;
use App\Models\Identity\User;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophyRelation;
use App\Models\Trophies\TrophySet;
use App\Models\Trophies\TrophySetRelation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Every factory must produce a record the database accepts, repeatedly and without collisions. */
class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        $models = [
            User::class, PsnAccount::class, Game::class, Edition::class, GameRelease::class, GameRelation::class,
            ExternalIdentifier::class, TrophySet::class, TrophyGroup::class, Trophy::class,
            TrophySetRelation::class, TrophyRelation::class,
        ];

        return array_combine(array_map(class_basename(...), $models), array_map(fn (string $m): array => [$m], $models));
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('models')]
    public function test_factory_creates_valid_distinct_records(string $model): void
    {
        /** @var callable(): mixed $makeFactory */
        $makeFactory = [$model, 'factory'];
        $factory = $makeFactory();
        $this->assertInstanceOf(Factory::class, $factory);

        $created = $factory->count(3)->create();

        $this->assertInstanceOf(Collection::class, $created);
        $this->assertCount(3, $created->pluck('id')->unique());
        $this->assertSame(3, $model::query()->whereKey($created->modelKeys())->count());
    }

    public function test_states_produce_valid_records(): void
    {
        $this->assertTrue(Game::factory()->retired()->create()->isRetired());
        $this->assertSame(PsnAccountStatus::Disabled, PsnAccount::factory()->disabled()->create()->status);
        $this->assertSame(TrophyGrade::Platinum, Trophy::factory()->platinum()->create()->grade);
        $this->assertTrue(Trophy::factory()->hidden()->create()->is_hidden);
        $this->assertNotNull(ExternalIdentifier::factory()->retired()->create()->retired_at);
        $this->assertEqualsCanonicalizing(['ps4', 'ps5'], GameRelease::factory()->onPlatforms(['ps4', 'ps5'])->create()->platforms->pluck('code')->all());
        $this->assertSame('eu', GameRelease::factory()->forRegion('eu')->create()->region_code);
    }

    /**
     * @return array<string, array{class-string<Model>, string}>
     */
    public static function everyRelationKind(): array
    {
        $cases = [];
        foreach ([GameRelation::class => GameRelationKind::class, TrophySetRelation::class => TrophySetRelationKind::class, TrophyRelation::class => TrophyRelationKind::class] as $model => $enum) {
            foreach ($enum::cases() as $case) {
                $cases[class_basename($model).'/'.$case->value] = [$model, $case->value];
            }
        }

        return $cases;
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('everyRelationKind')]
    public function test_relation_factories_produce_valid_rows_for_every_kind_whatever_the_id_order(string $model, string $kind): void
    {
        foreach (range(1, 4) as $_) {
            /** @var callable(): mixed $makeFactory */
            $makeFactory = [$model, 'factory'];
            $factory = $makeFactory();
            $this->assertInstanceOf(Factory::class, $factory);
            $created = $factory->state(['kind' => $kind])->create();
            $this->assertInstanceOf(Model::class, $created);
            $this->assertTrue($model::query()->whereKey($created->getKey())->exists());
        }
    }

    public function test_default_factories_never_combine_rows_of_different_owners(): void
    {
        $edition = Edition::factory()->create();
        $release = GameRelease::factory()->forEdition($edition)->create();
        $this->assertSame($edition->game_id, $release->game_id);

        $group = TrophyGroup::factory()->create();
        $trophy = Trophy::factory()->inGroup($group)->create();
        $this->assertSame($group->trophy_set_id, $trophy->trophy_set_id);

        $plain = Trophy::factory()->create();
        $this->assertSame($plain->group?->trophy_set_id, $plain->trophy_set_id);

        $relation = GameRelation::factory()->create();
        $this->assertNotSame($relation->from_game_id, $relation->to_game_id);
        $trophyRelation = TrophyRelation::factory()->create();
        $this->assertNotSame($trophyRelation->from?->trophy_set_id, $trophyRelation->to?->trophy_set_id);
    }

    public function test_convenience_queries_do_not_multiply_queries(): void
    {
        $game = Game::factory()->create();
        GameRelation::link(Game::factory()->create(), $game, GameRelationKind::SequelTo);

        DB::enableQueryLog();
        $game->sequels();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $queries, 'sequels() must be a single query');
    }
}
