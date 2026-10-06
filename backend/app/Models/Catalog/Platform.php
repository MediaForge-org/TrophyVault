<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

/** Reference data (seeded by migration); the code is the primary key. */
class Platform extends Model
{
    protected $table = 'platforms';

    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
