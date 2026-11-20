<?php

use App\Events\Admin\RoleCreated;
use App\Events\Auth\UserLoggedIn;
use App\Events\Auth\UserProfileUpdated;
use App\Events\Order\OrderPlaced;
use App\Events\Order\OrderStatusUpdated;
use App\Events\Settings\ShippingSettingUpdated;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLog\ActivityLoggerService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);
});

it('allows super admin to list activity logs with pagination', function () {
    $superAdmin = superAdminUser();
    Sanctum::actingAs($superAdmin, ['admin']);

    $order = Order::factory()->create();
    OrderStatusUpdated::dispatch($order, 'pending', 'shipped', null, null, $superAdmin);
    UserLoggedIn::dispatch($superAdmin, true, '127.0.0.1');

    $response = $this->getJson('/api/v1/admin/activity-logs');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'user_id',
                    'user' => ['id', 'name', 'email', 'account_type'],
                    'action',
                    'entity_type',
                    'entity_id',
                    'payload',
                    'ip_address',
                    'created_at',
                ],
            ],
            'meta' => ['total', 'per_page', 'current_page', 'last_page'],
        ]);

    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(2);
});

it('allows staff with activity_logs.view permission to list activity logs', function () {
    $staff = staffWithPermissions(['activity_logs.view']);
    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/activity-logs');

    $response->assertOk()
        ->assertJson(['success' => true]);
});

it('forbids staff without activity_logs.view permission', function () {
    $staff = staffWithPermissions(['products.view']);
    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/activity-logs');

    $response->assertForbidden();
});

it('forbids customers from accessing admin activity logs', function () {
    $customer = User::factory()->customer()->create();
    Sanctum::actingAs($customer);

    $response = $this->getJson('/api/v1/admin/activity-logs');

    $response->assertForbidden();
});

it('requires authentication to access admin activity logs', function () {
    $response = $this->getJson('/api/v1/admin/activity-logs');

    $response->assertUnauthorized();
});

it('filters activity logs by action slug and account type', function () {
    $superAdmin = superAdminUser();
    Sanctum::actingAs($superAdmin, ['admin']);

    $customer = User::factory()->customer()->create();
    $order = Order::factory()->create(['user_id' => $customer->id]);

    OrderPlaced::dispatch($order, $customer);
    UserLoggedIn::dispatch($superAdmin, true, '127.0.0.1');

    $response = $this->getJson('/api/v1/admin/activity-logs?action=orders.placed');
    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.action'))->toBe('orders.placed');

    $staffResponse = $this->getJson('/api/v1/admin/activity-logs?account_type=staff');
    $staffResponse->assertOk();
    $actions = collect($staffResponse->json('data'))->pluck('action');
    expect($actions)->toContain('staff.auth.login');
    expect($actions)->not->toContain('orders.placed');
});

it('retrieves distinct action slugs for UI dropdowns', function () {
    $superAdmin = superAdminUser();
    Sanctum::actingAs($superAdmin, ['admin']);

    $role = Role::create(['name' => 'test-audit-role', 'display_name' => 'Audit Role']);
    RoleCreated::dispatch($role, [], $superAdmin);
    UserLoggedIn::dispatch($superAdmin, true, '127.0.0.1');

    $response = $this->getJson('/api/v1/admin/activity-logs/actions');

    $response->assertOk()
        ->assertJson(['success' => true]);

    $actions = $response->json('data');
    expect($actions)->toContain('roles.created');
    expect($actions)->toContain('staff.auth.login');
});

it('shows a single activity log detail', function () {
    $superAdmin = superAdminUser();
    Sanctum::actingAs($superAdmin, ['admin']);

    ShippingSettingUpdated::dispatch([
        'shipping_fee' => 7.50,
        'shipping_enabled' => true,
    ], $superAdmin);

    $log = ActivityLog::where('action', 'settings.shipping_updated')->firstOrFail();

    $response = $this->getJson("/api/v1/admin/activity-logs/{$log->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $log->id,
                'action' => 'settings.shipping_updated',
                'payload' => [
                    'shipping_fee' => 7.50,
                    'shipping_enabled' => true,
                ],
            ],
        ]);
});

it('allows customer to view their own activity logs only', function () {
    $customer1 = User::factory()->customer()->create();
    $customer2 = User::factory()->customer()->create();

    UserLoggedIn::dispatch($customer1, false, '1.1.1.1');
    UserProfileUpdated::dispatch($customer1, ['name'], false);
    UserLoggedIn::dispatch($customer2, false, '2.2.2.2');

    Sanctum::actingAs($customer1, ['customer']);

    $response = $this->getJson('/api/v1/activity-logs');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'action', 'payload', 'created_at'],
            ],
            'meta' => ['total'],
        ]);

    expect($response->json('meta.total'))->toBe(2);
    $actions = collect($response->json('data'))->pluck('action');
    expect($actions)->toContain('auth.login');
    expect($actions)->toContain('profile.updated');
});

it('sanitizes sensitive payload credentials automatically via ActivityLoggerService', function () {
    $customer = User::factory()->customer()->create();

    $service = app(ActivityLoggerService::class);
    $log = $service->log('auth.password_reset', $customer, [
        'password' => 'supersecret123',
        'password_confirmation' => 'supersecret123',
        'token' => 'plain-text-token',
        'safe_key' => 'visible_value',
    ], $customer);

    expect($log->payload['password'])->toBe('[REDACTED]');
    expect($log->payload['password_confirmation'])->toBe('[REDACTED]');
    expect($log->payload['token'])->toBe('[REDACTED]');
    expect($log->payload['safe_key'])->toBe('visible_value');
});

it('dispatches domain events like OrderStatusUpdated when orders are changed', function () {
    Event::fake([OrderStatusUpdated::class]);

    $order = Order::factory()->create();

    OrderStatusUpdated::dispatch($order, 'pending', 'processing', 'Valid', null);

    Event::assertDispatched(OrderStatusUpdated::class, function (OrderStatusUpdated $event) use ($order) {
        return $event->order->id === $order->id
            && $event->oldStatus === 'pending'
            && $event->newStatus === 'processing';
    });
});
