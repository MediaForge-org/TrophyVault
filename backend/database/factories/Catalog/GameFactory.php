<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    protected $model = Game::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->lexify('Game ?????? ??????');

        return [
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'title' => $title,
            'sort_title' => Str::lower($title),
        ];
    }

    public function retired(): static
    {
        return $this->state(['retired_at' => now()]);
    }
}
