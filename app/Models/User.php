<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * An operator of the panel. There is no registration route and no e-mail
 * verification: accounts are created from the CLI with `php artisan aimemory:user`.
 * This app reads another product's data — the fewer public doors, the better.
 */
class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
