<?php

namespace App\Models\Catalog;

use App\Enums\GameRelationKind;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Trophies\TrophySet;
use Database\Factories\Catalog\GameFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'retired_at' => 'immutable_datetime'];
    }

    /** @return HasMany<Edition, $this> */
    public function editions(): HasMany
    {
        return $this->hasMany(Edition::class);
    }

    /** @return HasMany<GameRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(GameRelease::class);
    }

    /** @return HasMany<TrophySet, $this> */
    public function trophySets(): HasMany
    {
        return $this->hasMany(TrophySet::class);
    }

    /** @return HasMany<ExternalIdentifier, $this> */
    public function externalIdentifiers(): HasMany
    {
        return $this->hasMany(ExternalIdentifier::class);
    }

    /**
     * Relations in which this game is the "from" side (it is the remake/sequel/collection/...).
     *
     * @return HasMany<GameRelation, $this>
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(GameRelation::class, 'from_game_id');
    }

    /** @return HasMany<GameRelation, $this> */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(GameRelation::class, 'to_game_id');
    }

    /**
     * Games that are sequels of this one ("prequel_to" is not stored; it is sequel_to read backwards).
     *
     * @return Collection<int, Game>
     */
    public function sequels(): Collection
    {
        return self::query()
            ->whereIn('id', $this->incomingRelations()->where('kind', GameRelationKind::SequelTo)->select('from_game_id'))
            ->get();
    }

    /** True when the game is no longer part of the active catalog (retired, never deleted). */
    public function isRetired(): bool
    {
        return $this->retired_at !== null;
    }

    /**
     * Entities that are still part of the active catalog (retired ones stay referenced, never deleted).
     *
     * @param  Builder<Game>  $query
     * @return Builder<Game>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }
}
