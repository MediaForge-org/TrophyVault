<?php

namespace App\Enums;

/**
 * Relation between two trophies. Directed kinds: replaces (from = newer, to = older), autopop (from = source, to = target).
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum TrophyRelationKind: string implements PairKind
{
    case Equivalent = 'equivalent';
    case Similar = 'similar';
    case Related = 'related';
    case Replaces = 'replaces';
    case Autopop = 'autopop';

    public function isSymmetric(): bool
    {
        return in_array($this, [self::Equivalent, self::Similar, self::Related], true);
    }
}
