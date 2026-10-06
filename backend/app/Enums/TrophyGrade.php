<?php

namespace App\Enums;

/**
 * Trophy grade as defined by the platform (closed set).
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum TrophyGrade: string
{
    case Bronze = 'bronze';
    case Silver = 'silver';
    case Gold = 'gold';
    case Platinum = 'platinum';
}
