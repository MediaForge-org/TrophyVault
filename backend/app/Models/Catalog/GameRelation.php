<?php

namespace App\Models\Catalog;

use App\Enums\GameRelationKind;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Concerns\LinksPairs;
use Database\Factories\Catalog\GameRelationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameRelation extends Model
{
    /**
     * @use HasFactory<GameRelationFactory>
     * @use LinksPairs<Game>
     */
    use HasFactory, HasUuidPrimaryKey, LinksPairs;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['kind' => GameRelationKind::class];
    }

    protected static function pairColumns(): array
    {
        return ['from_game_id', 'to_game_id'];
    }

    /** @return BelongsTo<Game, $this> */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'from_game_id');
    }

    /** @return BelongsTo<Game, $this> */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'to_game_id');
    }
}
