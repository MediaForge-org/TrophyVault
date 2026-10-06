<?php

namespace Database\Factories\Catalog;

use App\Models\Catalog\Edition;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRelease>
 */
class GameReleaseFactory extends Factory
{
    protected $model = GameRelease::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'region_code' => 'unknown',
        ];
    }

    /** Places the release in the edition's game (an edition of another game is impossible). */
    public function forEdition(Edition $edition): static
    {
        return $this->state(['game_id' => $edition->game_id, 'edition_id' => $edition->id]);
    }

    public function forRegion(string $regionCode): static
    {
        return $this->state(['region_code' => $regionCode]);
    }

    /**
     * Creates the release together with its platform rows.
     *
     * @param  list<string>  $platformCodes
     */
    public function onPlatforms(array $platformCodes): static
    {
        return $this->afterCreating(function (GameRelease $release) use ($platformCodes): void {
            $release->onPlatforms($platformCodes);
        });
    }
}
