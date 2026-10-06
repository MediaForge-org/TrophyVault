<?php

namespace Database\Factories\Identity;

use App\Models\Identity\PsnAccount;
use App\Models\Identity\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PsnAccount>
 */
class PsnAccountFactory extends Factory
{
    protected $model = PsnAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'psn_account_id' => (string) fake()->unique()->numerify('####################'),
            'online_id' => fake()->unique()->userName(),
        ];
    }

    public function disabled(): static
    {
        return $this->state(['status' => 'disabled']);
    }
}
