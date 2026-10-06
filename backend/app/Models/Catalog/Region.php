<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/** Reference data (seeded by migration); 'global' and 'unknown' are real values. */
class Region extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
