<?php

namespace App\Enums;

/**
 * TrophyVault's own semantic classification of a trophy group (not the list's structure).
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum TrophyGroupClassification: string
{
    case Base = 'base';
    case Dlc = 'dlc';
    case Expansion = 'expansion';
    case FreeUpdate = 'free_update';
    case Online = 'online';
    case Challenge = 'challenge';
    case Other = 'other';
    case Unknown = 'unknown';
}
