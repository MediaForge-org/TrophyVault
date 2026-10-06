<?php

namespace App\Enums;

/**
 * Whether a linked PSN account is synchronised.
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum PsnAccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
