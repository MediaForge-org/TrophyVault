<?php

namespace Database\Factories\Trophies;

use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophySet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrophyGroup>
 */
class TrophyGroupFactory extends Factory
{
    protected $model = TrophyGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trophy_set_id' => TrophySet::factory(),
            'set_key' => 'default',
            'name' => 'Base Game',
            'ordinal' => 0,
        ];
    }

    public function addon(string $key, int $ordinal, string $name = 'Add-on'): static
    {
        return $this->state(['set_key' => $key, 'ordinal' => $ordinal, 'name' => $name]);
    }

    public function classified(string $classification): static
    {
        return $this->state(['classification' => $classification]);
    }
}
