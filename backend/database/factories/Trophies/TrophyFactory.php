<?php

namespace Database\Factories\Trophies;

use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophyGroup;
use App\Models\Trophies\TrophySet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trophy>
 */
class TrophyFactory extends Factory
{
    protected $model = Trophy::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trophy_set_id' => TrophySet::factory(),
            // Resolved after trophy_set_id: the group is created inside the same trophy set.
            'trophy_group_id' => fn (array $attributes): string => TrophyGroup::factory()->create(['trophy_set_id' => $attributes['trophy_set_id']])->id,
            'set_key' => fake()->unique()->numberBetween(0, 100000),
            'grade' => 'bronze',
            'name' => fake()->unique()->words(3, true),
            'ordinal' => fake()->numberBetween(0, 1000),
        ];
    }

    /** Creates the trophy inside an existing group (and therefore that group's trophy set). */
    public function inGroup(TrophyGroup $group): static
    {
        return $this->state(['trophy_set_id' => $group->trophy_set_id, 'trophy_group_id' => $group->id]);
    }

    public function platinum(): static
    {
        return $this->state(['grade' => 'platinum']);
    }

    public function hidden(): static
    {
        return $this->state(['is_hidden' => true]);
    }
}
