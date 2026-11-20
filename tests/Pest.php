<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Create an active staff user assigned a single role holding the given permissions.
 *
 * @param  array<int, string>  $permissions
 */
function staffWithPermissions(array $permissions = []): User
{
    $user = User::factory()->staff()->create();

    if ($permissions === []) {
        return $user;
    }

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(
            ['name' => $permission],
            ['module' => explode('.', $permission)[0], 'description' => null],
        );
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::create([
        'name' => 'test-role-'.fake()->unique()->numberBetween(1, 999999),
        'display_name' => 'Test Role',
    ]);

    $role->syncPermissions($permissions);
    $user->assignRole($role);

    return $user;
}

/**
 * Create a staff user holding the protected Super Admin role.
 */
function superAdminUser(): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate(
        ['name' => 'super-admin'],
        ['display_name' => 'Super Admin', 'is_system' => true],
    );

    $user = User::factory()->staff()->create();
    $user->assignRole($role);

    return $user;
}
