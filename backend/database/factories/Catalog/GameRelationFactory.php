<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRelation>
 */
class GameRelationFactory extends Factory
{
    protected $model = GameRelation::class;

    public function configure(): static
    {
        // Symmetric kinds must be stored as an ordered pair; keep every factory state valid.
        return $this->afterMaking(fn (GameRelation $relation) => $relation->orderPair());
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_game_id' => Game::factory(),
            'to_game_id' => Game::factory(),
            'kind' => 'sequel_to',
        ];
    }
}
