<?php

namespace App\Models\Trophies;

use App\Enums\TrophyRelationKind;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\LinksPairs;
use Database\Factories\Trophies\TrophyRelationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrophyRelation extends Model
{
    /**
     * @use HasFactory<TrophyRelationFactory>
     * @use LinksPairs<Trophy>
     */
    use HasFactory, HasUuidPrimaryKey, LinksPairs;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['kind' => TrophyRelationKind::class];
    }

    protected static function pairColumns(): array
    {
        return ['from_trophy_id', 'to_trophy_id'];
    }

    /** @return BelongsTo<Trophy, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Trophy::class, 'from_trophy_id');
    }

    /** @return BelongsTo<Trophy, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Trophy::class, 'to_trophy_id');
    }
}
