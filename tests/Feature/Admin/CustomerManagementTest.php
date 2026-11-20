<?php

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingAddress;
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

it('allows super admin to list customers with pagination and order counts', function () {
    $superAdmin = superAdminUser();
    $customers = User::factory()->customer()->count(5)->create();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/customers');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Customers retrieved successfully.',
        ])
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'orders_count',
                    'total_spent',
                    'created_at',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);

    expect(count($response->json('data')))->toBe(5);
});

it('allows staff with customers.view permission to list customers', function () {
    $staff = staffWithPermissions(['customers.view']);
    User::factory()->customer()->count(3)->create();

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/customers');
    $response->assertOk();
    expect(count($response->json('data')))->toBe(3);
});

it('excludes staff members from customer listing', function () {
    $superAdmin = superAdminUser();
    User::factory()->customer()->count(2)->create();
    User::factory()->staff()->count(3)->create();

    Sanctum::actingAs($superAdmin, ['admin']);

    $response = $this->getJson('/api/v1/admin/customers');
    $response->assertOk();

    expect($response->json('meta.total'))->toBe(2);
    foreach ($response->json('data') as $item) {
        $user = User::find($item['id']);
        expect($user->account_type)->toBe(AccountType::Customer);
    }
});

it('searches customers by name, email, and phone', function () {
    $superAdmin = superAdminUser();
    $customerA = User::factory()->customer()->create([
        'name' => 'Alice Montgomery',
        'email' => 'alice@fashion.com',
        'phone' => '+15551234567',
    ]);
    $customerB = User::factory()->customer()->create([
        'name' => 'Bob Kingston',
        'email' => 'bob@design.com',
        'phone' => '+15559876543',
    ]);

    Sanctum::actingAs($superAdmin, ['admin']);

    $byName = $this->getJson('/api/v1/admin/customers?search=Montgomery');
    $byName->assertOk();
    expect($byName->json('meta.total'))->toBe(1)
        ->and($byName->json('data.0.id'))->toBe($customerA->id);

    $byEmail = $this->getJson('/api/v1/admin/customers?search=design.com');
    $byEmail->assertOk();
    expect($byEmail->json('meta.total'))->toBe(1)
        ->and($byEmail->json('data.0.id'))->toBe($customerB->id);

    $byPhone = $this->getJson('/api/v1/admin/customers?search=1234567');
    $byPhone->assertOk();
    expect($byPhone->json('meta.total'))->toBe(1)
        ->and($byPhone->json('data.0.id'))->toBe($customerA->id);
});

it('denies staff without customers.view permission', function () {
    $staff = staffWithPermissions(['orders.view']);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson('/api/v1/admin/customers');
    $response->assertForbidden();
});

it('denies customer accounts from accessing customer management', function () {
    $customer = User::factory()->customer()->create();

    Sanctum::actingAs($customer, ['customer']);

    $response = $this->getJson('/api/v1/admin/customers');
    $response->assertForbidden();
});

it('denies unauthenticated guests from accessing customer management', function () {
    $response = $this->getJson('/api/v1/admin/customers');
    $response->assertUnauthorized();
});

it('allows staff to view customer details including addresses and financial totals', function () {
    $staff = staffWithPermissions(['customers.view']);
    $customer = User::factory()->customer()->create([
        'name' => 'Claire Fraser',
        'email' => 'claire@highlands.com',
    ]);

    $address = ShippingAddress::factory()->for($customer)->create([
        'city' => 'Inverness',
        'country' => 'GB',
        'is_default' => true,
    ]);

    // Paid order for 150.00
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'final_total' => 150.00,
    ]);

    // Pending unpaid order for 80.00 (should NOT be added to total_spent)
    Order::factory()->for($customer)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'final_total' => 80.00,
    ]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson("/api/v1/admin/customers/{$customer->id}");

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'name' => 'Claire Fraser',
                'email' => 'claire@highlands.com',
                'orders_count' => 2,
                'total_spent' => 150.00,
            ],
        ]);

    expect(count($response->json('data.shipping_addresses')))->toBe(1)
        ->and($response->json('data.shipping_addresses.0.city'))->toBe('Inverness');
});

it('returns 404 when customer id belongs to staff or does not exist', function () {
    $staff = staffWithPermissions(['customers.view']);
    $otherStaff = User::factory()->staff()->create();

    Sanctum::actingAs($staff, ['admin']);

    $nonExistent = $this->getJson('/api/v1/admin/customers/99999');
    $nonExistent->assertNotFound();

    $staffTarget = $this->getJson("/api/v1/admin/customers/{$otherStaff->id}");
    $staffTarget->assertNotFound();
});

it('allows staff to view customer order history paginated', function () {
    $staff = staffWithPermissions(['customers.view']);
    $customer = User::factory()->customer()->create();
    $address = ShippingAddress::factory()->for($customer)->create();

    Order::factory()->for($customer)->count(4)->create([
        'shipping_address_id' => $address->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
    ]);

    Sanctum::actingAs($staff, ['admin']);

    $response = $this->getJson("/api/v1/admin/customers/{$customer->id}/orders");

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'order_number',
                    'order_status',
                    'payment_status',
                    'final_total',
                ],
            ],
            'meta' => ['current_page', 'last_page', 'total'],
        ]);

    expect($response->json('meta.total'))->toBe(4);
});
