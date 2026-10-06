<?php

namespace App\Models\Catalog;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Trophies\TrophySet;
use Database\Factories\Catalog\GameReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameRelease extends Model
{
    /** @use HasFactory<GameReleaseFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'release_date' => 'immutable_date',
            'retired_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<Edition, $this> */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_code');
    }

    /** @return BelongsToMany<Platform, $this> */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'game_release_platforms', 'game_release_id', 'platform_code');
    }

    /**
     * @param  iterable<string>  $codes  platform codes, e.g. ['ps4', 'ps5']
     */
    public function onPlatforms(iterable $codes): static
    {
        $this->platforms()->syncWithoutDetaching(is_array($codes) ? $codes : iterator_to_array($codes, false));

        return $this;
    }

    /** @return BelongsToMany<TrophySet, $this> */
    public function trophySets(): BelongsToMany
    {
        return $this->belongsToMany(TrophySet::class, 'game_release_trophy_sets', 'game_release_id', 'trophy_set_id')
            ->withPivot('game_id');
    }

    /**
     * Links a trophy list to this release. The composite foreign keys reject a list of another game at the database level.
     */
    public function useTrophySet(TrophySet $set): static
    {
        $this->trophySets()->syncWithoutDetaching([(string) $set->id => ['game_id' => $this->game_id]]);

        return $this;
    }

    /** @return HasMany<ExternalIdentifier, $this> */
    public function externalIdentifiers(): HasMany
    {
        return $this->hasMany(ExternalIdentifier::class);
    }

    /**
     * Entities that are still part of the active catalog (retired ones stay referenced, never deleted).
     *
     * @param  Builder<GameRelease>  $query
     * @return Builder<GameRelease>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }
}
