<?php

namespace App\Models\Catalog;

use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Sync\Provider;
use App\Models\Trophies\TrophySet;
use Database\Factories\Catalog\ExternalIdentifierFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalIdentifier extends Model
{
    /** @use HasFactory<ExternalIdentifierFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['retired_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Provider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'provider_code');
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<GameRelease, $this> */
    public function gameRelease(): BelongsTo
    {
        return $this->belongsTo(GameRelease::class);
    }

    /** @return BelongsTo<TrophySet, $this> */
    public function trophySet(): BelongsTo
    {
        return $this->belongsTo(TrophySet::class);
    }

    /**
     * @param  Builder<ExternalIdentifier>  $query
     * @return Builder<ExternalIdentifier>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('retired_at');
    }
}
