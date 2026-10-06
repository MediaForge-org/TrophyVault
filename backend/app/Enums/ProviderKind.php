<?php

namespace App\Enums;

/**
 * Category of an information provider (not whether a connector exists).
 * Mirrors the CHECK constraint of the same name; EnumConsistencyTest keeps both in sync.
 */
enum ProviderKind: string
{
    case Platform = 'platform';
    case ThirdParty = 'third_party';
    case Community = 'community';
    case Manual = 'manual';
}
