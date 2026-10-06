<?php

namespace App\Models\Concerns;

use App\Enums\PairKind;
use Illuminate\Database\Eloquent\Model;

/**
 * For relation tables with from/to columns. Symmetric kinds are stored as an ordered pair
 * (from < to, enforced by a CHECK constraint); this takes care of the ordering.
 *
 * @template TEntity of Model
 */
trait LinksPairs
{
    /**
     * @return array{0: string, 1: string} names of the from and to columns
     */
    abstract protected static function pairColumns(): array;

    /**
     * @param  TEntity  $from
     * @param  TEntity  $to
     */
    public static function link(Model $from, Model $to, PairKind&\BackedEnum $kind): static
    {
        $fromId = self::uuidOf($from);
        $toId = self::uuidOf($to);

        // UUIDs are compared as lower-case hex strings, which matches PostgreSQL's byte-wise uuid ordering.
        if ($kind->isSymmetric() && strcmp($fromId, $toId) > 0) {
            [$fromId, $toId] = [$toId, $fromId];
        }

        [$fromColumn, $toColumn] = static::pairColumns();

        return static::query()->create([
            $fromColumn => $fromId,
            $toColumn => $toId,
            'kind' => $kind,
        ]);
    }

    /**
     * Swaps from/to when the kind is symmetric and the pair is not stored in canonical (ascending) order.
     * Factories call this so that any symmetric kind they are given yields a valid row.
     */
    public function orderPair(): static
    {
        [$fromColumn, $toColumn] = static::pairColumns();
        $kind = $this->getAttribute('kind');
        $from = $this->getAttribute($fromColumn);
        $to = $this->getAttribute($toColumn);

        if ($kind instanceof PairKind && $kind->isSymmetric() && is_string($from) && is_string($to) && strcmp(strtolower($from), strtolower($to)) > 0) {
            $this->setAttribute($fromColumn, $to);
            $this->setAttribute($toColumn, $from);
        }

        return $this;
    }

    private static function uuidOf(Model $model): string
    {
        $key = $model->getKey();

        if (! is_string($key)) {
            throw new \InvalidArgumentException('Relations can only link persisted models with UUID keys.');
        }

        // PostgreSQL stores uuids canonically in lower case; compare the same way.
        return strtolower($key);
    }
}
