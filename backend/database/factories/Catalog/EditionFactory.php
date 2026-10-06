<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Edition;
use App\Models\Catalog\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Edition>
 */
class EditionFactory extends Factory
{
    protected $model = Edition::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->bothify('Edition ######');

        return [
            'game_id' => Game::factory(),
            'slug' => Str::slug($name),
            'name' => $name,
        ];
    }
}
