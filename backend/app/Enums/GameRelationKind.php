<?php

namespace App\Enums;

/**
 * Relation between two games. Direction: from = the remake/remaster/sequel/collection, to = the original/predecessor/member. 'prequel_to' is not stored: it is sequel_to read backwards.
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum GameRelationKind: string implements PairKind
{
    case RemakeOf = 'remake_of';
    case RemasterOf = 'remaster_of';
    case SequelTo = 'sequel_to';
    case CollectionContains = 'collection_contains';
    case Related = 'related';

    public function isSymmetric(): bool
    {
        return in_array($this, [self::Related], true);
    }
}
