<?php

namespace Tests\Integration\Catalog;

use App\Enums\GameRelationKind;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AssertsDatabaseViolations;
use Tests\TestCase;

class GameRelationTest extends TestCase
{
    use AssertsDatabaseViolations;
    use RefreshDatabase;

    public function test_a_game_cannot_relate_to_itself(): void
    {
        $game = Game::factory()->create();

        $this->assertViolation(self::CHECK, fn () => GameRelation::link($game, $game, GameRelationKind::Related));
        $this->assertViolation(self::CHECK, fn () => GameRelation::link($game, $game, GameRelationKind::RemakeOf));
    }

    public function test_directed_kinds_keep_their_direction(): void
    {
        [$remake, $original] = [Game::factory()->create(), Game::factory()->create()];

        $relation = GameRelation::link($remake, $original, GameRelationKind::RemakeOf);

        $this->assertSame($remake->id, $relation->from_game_id);
        $this->assertSame($original->id, $relation->to_game_id);
        $this->assertSame(GameRelationKind::RemakeOf, $relation->kind);
    }

    public function test_symmetric_kinds_are_stored_once_as_an_ordered_pair(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];

        $first = GameRelation::link($a, $b, GameRelationKind::Related);

        $this->assertLessThan(0, strcmp($first->from_game_id, $first->to_game_id));
        // The reverse call resolves to the very same pair and is rejected as a duplicate.
        $this->assertViolation(self::UNIQUE, fn () => GameRelation::link($b, $a, GameRelationKind::Related));
    }

    public function test_an_unordered_symmetric_pair_is_rejected_by_the_database(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];
        [$low, $high] = strcmp($a->id, $b->id) < 0 ? [$a, $b] : [$b, $a];

        $this->assertViolation(self::CHECK, fn () => DB::table('game_relations')->insert([
            'from_game_id' => $high->id, 'to_game_id' => $low->id, 'kind' => 'related',
        ]));
    }

    public function test_contradictory_directed_relations_are_rejected(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];
        GameRelation::link($a, $b, GameRelationKind::SequelTo);

        $this->assertViolation(self::UNIQUE, fn () => GameRelation::link($b, $a, GameRelationKind::SequelTo));
        $this->assertViolation(self::UNIQUE, fn () => GameRelation::link($a, $b, GameRelationKind::SequelTo));
    }

    public function test_different_kinds_between_the_same_games_are_allowed(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];
        GameRelation::link($a, $b, GameRelationKind::RemasterOf);
        GameRelation::link($a, $b, GameRelationKind::Related);

        $this->assertSame(2, GameRelation::query()->count());
    }

    public function test_prequel_is_not_stored_it_is_a_sequel_read_backwards(): void
    {
        [$first, $second, $third] = [Game::factory()->create(), Game::factory()->create(), Game::factory()->create()];
        GameRelation::link($second, $first, GameRelationKind::SequelTo);
        GameRelation::link($third, $second, GameRelationKind::SequelTo);

        $this->assertSame([$second->id], $first->sequels()->pluck('id')->all());
        $this->assertSame([$third->id], $second->sequels()->pluck('id')->all());
        $this->assertNotContains('prequel_to', array_map(fn (GameRelationKind $k): string => $k->value, GameRelationKind::cases()));
    }

    public function test_a_collection_contains_its_members(): void
    {
        $collection = Game::factory()->create();
        $members = Game::factory()->count(3)->create();
        foreach ($members as $member) {
            GameRelation::link($collection, $member, GameRelationKind::CollectionContains);
        }

        $this->assertCount(3, $collection->outgoingRelations);
        $member = $members->first();
        $this->assertNotNull($member);
        $this->assertCount(1, $member->incomingRelations);
    }

    public function test_unknown_kinds_are_rejected(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];

        $this->assertViolation(self::CHECK, fn () => DB::table('game_relations')->insert([
            'from_game_id' => $a->id, 'to_game_id' => $b->id, 'kind' => 'prequel_to',
        ]));
    }

    public function test_relations_are_removed_with_a_game_that_has_no_other_dependents(): void
    {
        [$a, $b] = [Game::factory()->create(), Game::factory()->create()];
        GameRelation::link($a, $b, GameRelationKind::Related);

        $a->delete();

        $this->assertSame(0, GameRelation::query()->count());
    }
}
