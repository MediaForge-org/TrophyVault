<?php

namespace App\Enums;

/**
 * Relation between two trophy sets. Directed kinds (autopop, partial_autopop): from = source list whose trophies pop the target's (to).
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum TrophySetRelationKind: string implements PairKind
{
    case PlatformStack = 'platform_stack';
    case RegionalStack = 'regional_stack';
    case RemasterStack = 'remaster_stack';
    case Equivalent = 'equivalent';
    case Related = 'related';
    case Autopop = 'autopop';
    case PartialAutopop = 'partial_autopop';

    public function isSymmetric(): bool
    {
        return in_array($this, [self::PlatformStack, self::RegionalStack, self::RemasterStack, self::Equivalent, self::Related], true);
    }
}
