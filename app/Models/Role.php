<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    /**
     * Roles always belong to the "web" guard. Pinning the guard keeps role
     * resolution stable when a request switches the default auth guard to
     * "sanctum" (which has no user provider).
     */
    protected $guard_name = 'web';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
        'guard_name',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }
}
