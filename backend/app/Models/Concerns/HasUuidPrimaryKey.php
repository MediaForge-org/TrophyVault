<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Primary keys are UUIDv7 (ADR 0007). Laravel generates them client-side (time-ordered),
 * the database default uuidv7() covers inserts that bypass Eloquent.
 */
trait HasUuidPrimaryKey
{
    use HasUuids;
}
