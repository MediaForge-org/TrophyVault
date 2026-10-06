<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\ExternalIdentifier;
use App\Models\Catalog\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalIdentifier>
 */
class ExternalIdentifierFactory extends Factory
{
    protected $model = ExternalIdentifier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_code' => 'psn',
            'namespace' => 'np_communication_id',
            'value' => 'NPWR'.fake()->unique()->numerify('#####').'_00',
            'game_id' => Game::factory(),
        ];
    }

    public function retired(): static
    {
        return $this->state(['retired_at' => now()]);
    }
}
