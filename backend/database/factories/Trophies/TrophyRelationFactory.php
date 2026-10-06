<?php

namespace Database\Factories\Trophies;

use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrophyRelation>
 */
class TrophyRelationFactory extends Factory
{
    protected $model = TrophyRelation::class;

    public function configure(): static
    {
        // Symmetric kinds must be stored as an ordered pair; keep every factory state valid.
        return $this->afterMaking(fn (TrophyRelation $relation) => $relation->orderPair());
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_trophy_id' => Trophy::factory(),
            'to_trophy_id' => Trophy::factory(),
            'kind' => 'replaces',
        ];
    }
}
