<?php

use App\Models\Coupon;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

function actingAsCouponStaff(string $permission): User
{
    $user = staffWithPermissions([$permission]);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

test('guest cannot access admin coupons', function () {
    $this->getJson('/api/v1/admin/coupons')->assertUnauthorized();
    $this->postJson('/api/v1/admin/coupons', [])->assertUnauthorized();
});

test('staff without coupons.view cannot list or view coupons', function () {
    $user = staffWithPermissions(['categories.view']);
    Sanctum::actingAs($user, ['admin']);

    $coupon = Coupon::create([
        'code' => 'TEST10',
        'type' => 'percentage',
        'value' => 10,
        'min_order_amount' => 50,
        'is_active' => true,
    ]);

    $this->getJson('/api/v1/admin/coupons')->assertForbidden();
    $this->getJson("/api/v1/admin/coupons/{$coupon->id}")->assertForbidden();
});

test('staff with coupons.view can list and filter coupons', function () {
    actingAsCouponStaff('coupons.view');

    Coupon::create([
        'code' => 'ALPHA10',
        'type' => 'percentage',
        'value' => 10,
        'min_order_amount' => 50,
        'is_active' => true,
    ]);

    Coupon::create([
        'code' => 'BETA20',
        'type' => 'fixed',
        'value' => 20,
        'min_order_amount' => 100,
        'is_active' => false,
    ]);

    // List all
    $response = $this->getJson('/api/v1/admin/coupons')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(count($response->json('data')))->toBe(2);

    // Search by code
    $searchRes = $this->getJson('/api/v1/admin/coupons?search=alpha')
        ->assertOk();
    expect(count($searchRes->json('data')))->toBe(1);
    expect($searchRes->json('data.0.code'))->toBe('ALPHA10');

    // Filter by type
    $typeRes = $this->getJson('/api/v1/admin/coupons?type=fixed')
        ->assertOk();
    expect(count($typeRes->json('data')))->toBe(1);
    expect($typeRes->json('data.0.code'))->toBe('BETA20');

    // Filter by is_active
    $activeRes = $this->getJson('/api/v1/admin/coupons?is_active=1')
        ->assertOk();
    expect(count($activeRes->json('data')))->toBe(1);
    expect($activeRes->json('data.0.code'))->toBe('ALPHA10');
});

test('staff with coupons.view can view a single coupon', function () {
    actingAsCouponStaff('coupons.view');

    $coupon = Coupon::create([
        'code' => 'VIEWME',
        'type' => 'percentage',
        'value' => 15,
        'min_order_amount' => 30,
        'usage_limit' => 200,
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/admin/coupons/{$coupon->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'VIEWME')
        ->assertJsonPath('data.value', fn ($val) => (float) $val === 15.0)
        ->assertJsonPath('data.usage_limit', 200);
});

test('staff with coupons.manage can create a percentage coupon', function () {
    actingAsCouponStaff('coupons.manage');

    $payload = [
        'code' => 'save15',
        'type' => 'percentage',
        'value' => 15.5,
        'min_order_amount' => 50,
        'usage_limit' => 500,
        'valid_from' => now()->toIso8601String(),
        'valid_until' => now()->addDays(30)->toIso8601String(),
        'is_active' => true,
    ];

    $response = $this->postJson('/api/v1/admin/coupons', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.code', 'SAVE15')
        ->assertJsonPath('data.value', 15.5);

    $this->assertDatabaseHas('coupons', [
        'code' => 'SAVE15',
        'type' => 'percentage',
        'is_active' => true,
    ]);
});

test('creating percentage coupon with value greater than 100 fails', function () {
    actingAsCouponStaff('coupons.manage');

    $this->postJson('/api/v1/admin/coupons', [
        'code' => 'INVALIDPCT',
        'type' => 'percentage',
        'value' => 105,
    ])->assertUnprocessable()->assertJsonValidationErrors(['value']);
});

test('creating coupon with duplicate code fails', function () {
    actingAsCouponStaff('coupons.manage');

    Coupon::create([
        'code' => 'EXISTING',
        'type' => 'fixed',
        'value' => 10,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/admin/coupons', [
        'code' => 'existing',
        'type' => 'fixed',
        'value' => 20,
    ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

test('staff with coupons.manage can update a coupon and toggle activation', function () {
    actingAsCouponStaff('coupons.manage');

    $coupon = Coupon::create([
        'code' => 'TOGGLEME',
        'type' => 'fixed',
        'value' => 25,
        'min_order_amount' => 100,
        'is_active' => true,
    ]);

    $this->putJson("/api/v1/admin/coupons/{$coupon->id}", [
        'value' => 30,
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.value', fn ($val) => (float) $val === 30.0)
        ->assertJsonPath('data.is_active', false);

    expect($coupon->fresh()->is_active)->toBeFalse();
    expect((float) $coupon->fresh()->value)->toBe(30.0);
});

test('staff with coupons.manage can delete a coupon', function () {
    actingAsCouponStaff('coupons.manage');

    $coupon = Coupon::create([
        'code' => 'DELETEME',
        'type' => 'percentage',
        'value' => 5,
        'is_active' => true,
    ]);

    $this->deleteJson("/api/v1/admin/coupons/{$coupon->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
});
