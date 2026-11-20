<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('guest cannot access customer address endpoints', function () {
    $this->getJson('/api/v1/addresses')->assertUnauthorized();
    $this->postJson('/api/v1/addresses', [])->assertUnauthorized();
    $this->getJson('/api/v1/addresses/1')->assertUnauthorized();
    $this->putJson('/api/v1/addresses/1', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/addresses/1')->assertUnauthorized();
    $this->patchJson('/api/v1/addresses/1/default')->assertUnauthorized();
});

test('customer can create their first address and it automatically becomes default', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $payload = [
        'name' => 'Alice Smith',
        'email' => 'alice@example.com',
        'phone' => '+1234567890',
        'address' => '742 Evergreen Terrace',
        'city' => 'Springfield',
        'state' => 'IL',
        'postal_code' => '62704',
        'country' => 'United States',
        'is_default' => false, // even if passed false, first address becomes default
    ];

    $response = $this->postJson('/api/v1/addresses', $payload)
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Alice Smith')
        ->assertJsonPath('data.email', 'alice@example.com')
        ->assertJsonPath('data.city', 'Springfield')
        ->assertJsonPath('data.is_default', true);

    $this->assertDatabaseHas('shipping_addresses', [
        'user_id' => $customer->id,
        'name' => 'Alice Smith',
        'is_default' => true,
    ]);
});

test('address creation validates all required fields and email format', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/addresses', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name', 'email', 'phone', 'address', 'city', 'state', 'postal_code', 'country',
        ]);

    $this->postJson('/api/v1/addresses', [
        'name' => 'Bob',
        'email' => 'not-an-email',
        'phone' => '123',
        'address' => 'Street',
        'city' => 'City',
        'state' => 'State',
        'postal_code' => '123',
        'country' => 'Country',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

test('creating an address with is_default sets previous default to false', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr1 = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Home',
        'email' => 'home@example.com',
        'phone' => '1111111111',
        'address' => '1st Street',
        'city' => 'City A',
        'state' => 'State A',
        'postal_code' => '11111',
        'country' => 'Country A',
        'is_default' => true,
    ]);

    $payload = [
        'name' => 'Work',
        'email' => 'work@example.com',
        'phone' => '2222222222',
        'address' => '2nd Avenue',
        'city' => 'City B',
        'state' => 'State B',
        'postal_code' => '22222',
        'country' => 'Country B',
        'is_default' => true,
    ];

    $response = $this->postJson('/api/v1/addresses', $payload)
        ->assertCreated()
        ->assertJsonPath('data.name', 'Work')
        ->assertJsonPath('data.is_default', true);

    expect($addr1->fresh()->is_default)->toBeFalse();
    expect(
        ShippingAddress::where('user_id', $customer->id)->where('is_default', true)->count()
    )->toBe(1);
});

test('customer can list addresses with default address listed first', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr1 = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Non-default Address',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'address' => 'Address 1',
        'city' => 'City 1',
        'state' => 'State 1',
        'postal_code' => '12345',
        'country' => 'Country 1',
        'is_default' => false,
    ]);

    $addr2 = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Default Address',
        'email' => 'test@example.com',
        'phone' => '1234567890',
        'address' => 'Address 2',
        'city' => 'City 2',
        'state' => 'State 2',
        'postal_code' => '12345',
        'country' => 'Country 2',
        'is_default' => true,
    ]);

    $response = $this->getJson('/api/v1/addresses')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(count($response->json('data')))->toBe(2);
    expect($response->json('data.0.id'))->toBe($addr2->id); // default address first
    expect($response->json('data.0.is_default'))->toBeTrue();
});

test('customer can view a single address', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Test View',
        'email' => 'view@example.com',
        'phone' => '9876543210',
        'address' => 'View St',
        'city' => 'View City',
        'state' => 'View State',
        'postal_code' => '54321',
        'country' => 'View Country',
        'is_default' => true,
    ]);

    $this->getJson("/api/v1/addresses/{$addr->id}")
        ->assertOk()
        ->assertJsonPath('data.name', 'Test View')
        ->assertJsonPath('data.postal_code', '54321');
});

test('IDOR protection: customer cannot access or mutate another customers address', function () {
    $userA = User::factory()->create(['account_type' => 'customer']);
    $userB = User::factory()->create(['account_type' => 'customer']);

    $addrA = ShippingAddress::create([
        'user_id' => $userA->id,
        'name' => 'User A Address',
        'email' => 'a@example.com',
        'phone' => '1111111111',
        'address' => 'A St',
        'city' => 'A City',
        'state' => 'A State',
        'postal_code' => '11111',
        'country' => 'US',
        'is_default' => true,
    ]);

    Sanctum::actingAs($userB);

    // Cannot view
    $this->getJson("/api/v1/addresses/{$addrA->id}")->assertNotFound();

    // Cannot update
    $this->putJson("/api/v1/addresses/{$addrA->id}", [
        'name' => 'Hacked Name',
    ])->assertNotFound();

    // Cannot delete
    $this->deleteJson("/api/v1/addresses/{$addrA->id}")->assertNotFound();

    // Cannot set default
    $this->patchJson("/api/v1/addresses/{$addrA->id}/default")->assertNotFound();

    // Verify User A's address is untouched
    expect($addrA->fresh()->name)->toBe('User A Address');
});

test('customer can update an address', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'phone' => '1234567890',
        'address' => 'Old Address',
        'city' => 'Old City',
        'state' => 'Old State',
        'postal_code' => '12345',
        'country' => 'Old Country',
        'is_default' => true,
    ]);

    $this->putJson("/api/v1/addresses/{$addr->id}", [
        'name' => 'New Name',
        'city' => 'New City',
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.city', 'New City')
        ->assertJsonPath('data.email', 'old@example.com');

    expect($addr->fresh()->name)->toBe('New Name');
});

test('customer can set an address as default via default endpoint', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr1 = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Address 1',
        'email' => 'addr1@example.com',
        'phone' => '1234567890',
        'address' => 'Street 1',
        'city' => 'City 1',
        'state' => 'State 1',
        'postal_code' => '12345',
        'country' => 'US',
        'is_default' => true,
    ]);

    $addr2 = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Address 2',
        'email' => 'addr2@example.com',
        'phone' => '1234567890',
        'address' => 'Street 2',
        'city' => 'City 2',
        'state' => 'State 2',
        'postal_code' => '12345',
        'country' => 'US',
        'is_default' => false,
    ]);

    $this->patchJson("/api/v1/addresses/{$addr2->id}/default")
        ->assertOk()
        ->assertJsonPath('data.id', $addr2->id)
        ->assertJsonPath('data.is_default', true);

    expect($addr1->fresh()->is_default)->toBeFalse();
    expect($addr2->fresh()->is_default)->toBeTrue();
});

test('customer cannot delete an address linked to an order due to restrict constraint', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'Linked Address',
        'email' => 'linked@example.com',
        'phone' => '1234567890',
        'address' => 'Order Blvd',
        'city' => 'Order City',
        'state' => 'Order State',
        'postal_code' => '99999',
        'country' => 'US',
        'is_default' => true,
    ]);

    // Create an order referencing this address
    Order::create([
        'order_number' => 'ORD-2026-999999',
        'user_id' => $customer->id,
        'shipping_address_id' => $addr->id,
        'currency' => 'USD',
        'subtotal' => 100,
        'shipping_charge' => 5,
        'final_total' => 105,
        'order_status' => OrderStatus::Pending,
        'payment_status' => PaymentStatus::Pending,
        'ship_name' => 'Linked Address',
        'ship_email' => 'linked@example.com',
        'ship_phone' => '1234567890',
        'ship_address' => 'Order Blvd',
        'ship_city' => 'Order City',
        'ship_state' => 'Order State',
        'ship_postal_code' => '99999',
        'ship_country' => 'US',
    ]);

    // Attempt to delete should fail with validation error
    $this->deleteJson("/api/v1/addresses/{$addr->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address'])
        ->assertJsonPath('errors.address.0', 'This shipping address cannot be deleted because it is associated with existing orders.');

    $this->assertDatabaseHas('shipping_addresses', ['id' => $addr->id]);
});

test('customer can delete an unlinked address', function () {
    $customer = User::factory()->create(['account_type' => 'customer']);
    Sanctum::actingAs($customer);

    $addr = ShippingAddress::create([
        'user_id' => $customer->id,
        'name' => 'To Delete',
        'email' => 'del@example.com',
        'phone' => '1234567890',
        'address' => 'Delete St',
        'city' => 'Delete City',
        'state' => 'Delete State',
        'postal_code' => '00000',
        'country' => 'US',
        'is_default' => true,
    ]);

    $this->deleteJson("/api/v1/addresses/{$addr->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->assertDatabaseMissing('shipping_addresses', ['id' => $addr->id]);
});
