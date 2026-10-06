<?php

namespace App\Models\Sync;

use App\Enums\ProviderKind;
use Illuminate\Database\Eloquent\Model;

/** An information source (psn, psnprofiles, manual, ...). Says nothing about whether a connector exists. */
class Provider extends Model
{
    protected $primaryKey = 'code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['kind' => ProviderKind::class];
    }
}
