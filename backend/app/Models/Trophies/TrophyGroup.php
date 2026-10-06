<?php

namespace App\Models\Trophies;

use App\Enums\ClassificationOrigin;
use App\Enums\TrophyGroupClassification;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\Trophies\TrophyGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrophyGroup extends Model
{
    /** @use HasFactory<TrophyGroupFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'classification' => TrophyGroupClassification::class,
            'classification_origin' => ClassificationOrigin::class,
        ];
    }

    /** @return BelongsTo<TrophySet, $this> */
    public function trophySet(): BelongsTo
    {
        return $this->belongsTo(TrophySet::class);
    }

    /** @return HasMany<Trophy, $this> */
    public function trophies(): HasMany
    {
        return $this->hasMany(Trophy::class)->orderBy('ordinal');
    }
}
