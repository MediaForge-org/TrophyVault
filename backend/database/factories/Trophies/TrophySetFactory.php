<?php

namespace Database\Factories\Trophies;

use App\Models\Catalog\Game;
use App\Models\Trophies\TrophySet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrophySet>
 */
class TrophySetFactory extends Factory
{
    protected $model = TrophySet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => fake()->unique()->lexify('Game ?????? ??????').' Trophies',
        ];
    }

    public function retired(): static
    {
        return $this->state(['retired_at' => now()]);
    }

    /**
     * Adds the platform labels after creation.
     *
     * @param  list<string>  $platformCodes
     */
    public function onPlatforms(array $platformCodes): static
    {
        return $this->afterCreating(function (TrophySet $set) use ($platformCodes): void {
            $set->platforms()->syncWithoutDetaching($platformCodes);
        });
    }
}
