<?php

namespace App\Models\Trophies;

use App\Enums\TrophySetRelationKind;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\LinksPairs;
use Database\Factories\Trophies\TrophySetRelationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrophySetRelation extends Model
{
    /**
     * @use HasFactory<TrophySetRelationFactory>
     * @use LinksPairs<TrophySet>
     */
    use HasFactory, HasUuidPrimaryKey, LinksPairs;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['kind' => TrophySetRelationKind::class];
    }

    protected static function pairColumns(): array
    {
        return ['from_trophy_set_id', 'to_trophy_set_id'];
    }

    /** @return BelongsTo<TrophySet, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(TrophySet::class, 'from_trophy_set_id');
    }

    /** @return BelongsTo<TrophySet, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(TrophySet::class, 'to_trophy_set_id');
    }
}
