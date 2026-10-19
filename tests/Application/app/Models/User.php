<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Minimal owner entity for the dummy consumer app. Stands in for whatever
 * principal a real consumer binds voting sessions to.
 */
class User extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = ['id'];
}
