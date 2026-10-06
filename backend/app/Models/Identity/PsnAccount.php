<?php

namespace App\Models\Identity;

use App\Enums\PsnAccountStatus;
use App\Models\Concerns\HasUuidPrimaryKey;
use App\Models\Trophies\Trophy;
use App\Models\Trophies\TrophySet;
use Carbon\CarbonImmutable;
use Database\Factories\Identity\PsnAccountFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

class PsnAccount extends Model
{
    /** @use HasFactory<PsnAccountFactory> */
    use HasFactory, HasUuidPrimaryKey;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => PsnAccountStatus::class,
            'profile' => 'array',
            'last_synced_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Trophy lists this account has (library membership + sync metadata).
     *
     * @return BelongsToMany<TrophySet, $this>
     */
    public function trophySets(): BelongsToMany
    {
        return $this->belongsToMany(TrophySet::class, 'psn_account_trophy_sets')
            ->withPivot(['first_seen_at', 'last_synced_at', 'provider_reported_progress'])
            ->withTimestamps();
    }

    /**
     * Earned trophies only (absence of a row = not earned).
     *
     * @return BelongsToMany<Trophy, $this>
     */
    public function earnedTrophies(): BelongsToMany
    {
        return $this->belongsToMany(Trophy::class, 'psn_account_trophies')
            ->withPivot(['trophy_set_id', 'earned_at'])
            ->withTimestamps();
    }

    /**
     * Records that this account holds a trophy list (idempotent).
     */
    public function holdTrophySet(TrophySet $set, ?DateTimeInterface $seenAt = null): static
    {
        $this->trophySets()->syncWithoutDetaching([
            (string) $set->id => ['first_seen_at' => self::instant($seenAt ?? CarbonImmutable::now())],
        ]);

        return $this;
    }

    /**
     * Records an earned trophy (idempotent). The trophy's own set is used, so a trophy can never be filed under a
     * different set, and the set is added to the account's library if needed. `$earnedAt` NULL = time unknown.
     * Instants are normalised to UTC with offset before they reach the query builder (which would drop the offset).
     */
    public function earn(Trophy $trophy, ?DateTimeInterface $earnedAt = null): static
    {
        $set = $trophy->trophySet;
        if ($set === null) {
            throw new InvalidArgumentException('The trophy has no trophy set.');
        }

        $this->holdTrophySet($set);
        $this->earnedTrophies()->syncWithoutDetaching([
            (string) $trophy->id => [
                'trophy_set_id' => (string) $set->id,
                'earned_at' => $earnedAt === null ? null : self::instant($earnedAt),
            ],
        ]);

        return $this;
    }

    private static function instant(DateTimeInterface $at): string
    {
        return CarbonImmutable::instance($at)->utc()->format('Y-m-d H:i:sP');
    }
}
