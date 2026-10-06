<?php

namespace App\Models\Trophies;

use App\Enums\TrophyGrade;
use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\Trophies\TrophyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trophy extends Model
{
    /** @use HasFactory<TrophyFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['grade' => TrophyGrade::class, 'is_hidden' => 'boolean', 'metadata' => 'array'];
    }

    /** @return BelongsTo<TrophySet, $this> */
    public function trophySet(): BelongsTo
    {
        return $this->belongsTo(TrophySet::class);
    }

    /** @return BelongsTo<TrophyGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(TrophyGroup::class, 'trophy_group_id');
    }
}
