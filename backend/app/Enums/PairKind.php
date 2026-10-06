<?php

namespace App\Enums;

/**
 * A kind of relation between two rows of the same table (games, trophy sets, trophies).
 * Symmetric kinds are stored once as an ordered pair; directed kinds keep their direction.
 */
interface PairKind
{
    public function isSymmetric(): bool;
}
