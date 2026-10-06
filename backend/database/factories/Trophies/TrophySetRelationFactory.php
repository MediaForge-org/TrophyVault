<?php

namespace Database\Factories\Trophies;

use App\Models\Trophies\TrophySet;
use App\Models\Trophies\TrophySetRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrophySetRelation>
 */
class TrophySetRelationFactory extends Factory
{
    protected $model = TrophySetRelation::class;

    public function configure(): static
    {
        // Symmetric kinds must be stored as an ordered pair; keep every factory state valid.
        return $this->afterMaking(fn (TrophySetRelation $relation) => $relation->orderPair());
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'from_trophy_set_id' => TrophySet::factory(),
            'to_trophy_set_id' => TrophySet::factory(),
            'kind' => 'autopop',
        ];
    }
}
