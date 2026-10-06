<?php

namespace App\Models\Trophies;

use App\Enums\TrophyGrade;
use App\Models\Catalog\ExternalIdentifier;
use App\Models\Catalog\Game;
use App\Models\Catalog\GameRelease;
use App\Models\Catalog\Platform;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\Trophies\TrophySetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrophySet extends Model
{
    /** @use HasFactory<TrophySetFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'retired_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsToMany<Platform, $this> */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class, 'trophy_set_platforms', 'trophy_set_id', 'platform_code');
    }

    /** @return BelongsToMany<GameRelease, $this> */
    public function releases(): BelongsToMany
    {
        return $this->belongsToMany(GameRelease::class, 'game_release_trophy_sets', 'trophy_set_id', 'game_release_id')
            ->withPivot('game_id');
    }

    /** @return HasMany<TrophyGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(TrophyGroup::class)->orderBy('ordinal');
    }

    /** @return HasMany<Trophy, $this> */
    public function trophies(): HasMany
    {
        return $this->hasMany(Trophy::class)->orderBy('ordinal');
    }

    /** @return HasMany<ExternalIdentifier, $this> */
    public function externalIdentifiers(): HasMany
    {
        return $this->hasMany(ExternalIdentifier::class);
    }

    /** Platinum status is derived: a list without a platinum trophy is simply "not applicable". */
    public function hasPlatinum(): bool
    {
        return $this->trophies()->where('grade', TrophyGrade::Platinum)->exists();
    }

    /**
     * Entities that are still part of the active catalog (retired ones stay referenced, never deleted).
     *
     * @param  Builder<TrophySet>  $query
     * @return Builder<TrophySet>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }
}
