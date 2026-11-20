<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    /**
     * Permissions always belong to the "web" guard. Pinning the guard keeps
     * permission lookups stable while authenticated with Sanctum tokens.
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'module',
        'description',
        'guard_name',
    ];
}
