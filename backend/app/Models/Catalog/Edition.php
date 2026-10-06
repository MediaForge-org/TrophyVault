<?php

namespace App\Models\Catalog;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\Catalog\EditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Edition extends Model
{
    /** @use HasFactory<EditionFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return HasMany<GameRelease, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(GameRelease::class);
    }
}
