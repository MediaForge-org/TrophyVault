<?php

namespace App\Enums;

/**
 * Who set a classification; manual values are never overwritten by imports.
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum ClassificationOrigin: string
{
    case Auto = 'auto';
    case Manual = 'manual';
}
