<?php

namespace App\Models\Identity;

use App\Models\Concerns\HasUuidPrimaryKey;
use Database\Factories\Identity\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuidPrimaryKey, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    /** @return HasMany<PsnAccount, $this> */
    public function psnAccounts(): HasMany
    {
        return $this->hasMany(PsnAccount::class);
    }
}
