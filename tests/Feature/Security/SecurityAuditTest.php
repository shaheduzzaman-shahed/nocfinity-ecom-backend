<?php

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('responses contain standard HTTP security headers', function () {
    $response = $this->getJson('/api/health');

    $response->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-XSS-Protection', '1; mode=block')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

test('customer tokens are strictly forbidden from accessing admin endpoints', function () {
    $customer = User::factory()->create([
        'account_type' => AccountType::Customer,
    ]);

    Sanctum::actingAs($customer, ['*']);

    $endpoints = [
        ['GET', '/api/v1/admin/dashboard'],
        ['GET', '/api/v1/admin/customers'],
        ['GET', '/api/v1/admin/orders'],
        ['GET', '/api/v1/admin/staff'],
        ['GET', '/api/v1/admin/roles'],
        ['GET', '/api/v1/admin/activity-logs'],
    ];

    foreach ($endpoints as [$method, $uri]) {
        $response = $this->json($method, $uri);
        $response->assertStatus(403);
    }
});

test('deactivated customer or staff account is rejected', function () {
    $inactiveCustomer = User::factory()->create([
        'account_type' => AccountType::Customer,
        'is_active' => false,
    ]);

    Sanctum::actingAs($inactiveCustomer, ['*']);

    $this->getJson('/api/v1/addresses')->assertStatus(403)
        ->assertJson(['message' => 'Your account has been deactivated.']);
});

test('IDOR protection prevents customer from viewing another customers order', function () {
    $customerA = User::factory()->create(['account_type' => AccountType::Customer]);
    $customerB = User::factory()->create(['account_type' => AccountType::Customer]);

    $addressB = ShippingAddress::create([
        'user_id' => $customerB->id,
        'name' => 'Customer B',
        'email' => 'customerb@example.com',
        'phone' => '+15559998888',
        'address' => '456 Elm St',
        'city' => 'Metropolis',
        'state' => 'NY',
        'postal_code' => '10001',
        'country' => 'US',
        'is_default' => true,
    ]);

    $orderB = Order::create([
        'order_number' => 'ORD-2026-999999',
        'user_id' => $customerB->id,
        'shipping_address_id' => $addressB->id,
        'order_status' => OrderStatus::Paid,
        'payment_status' => PaymentStatus::Paid,
        'currency' => 'USD',
        'subtotal' => 100.00,
        'final_total' => 100.00,
        'ship_name' => 'Customer B',
        'ship_email' => 'customerb@example.com',
        'ship_phone' => '+15559998888',
        'ship_address' => '456 Elm St',
        'ship_city' => 'Metropolis',
        'ship_state' => 'NY',
        'ship_postal_code' => '10001',
        'ship_country' => 'US',
    ]);

    Sanctum::actingAs($customerA, ['*']);

    // Attempting to access order B should result in 404 (not found) rather than disclosing existence
    $this->getJson("/api/v1/orders/{$orderB->order_number}")->assertNotFound();
    $this->getJson("/api/v1/orders/{$orderB->id}")->assertNotFound();
    $this->postJson("/api/v1/orders/{$orderB->id}/cancel")->assertNotFound();
});

test('IDOR protection prevents customer from viewing or updating another customers address', function () {
    $customerA = User::factory()->create(['account_type' => AccountType::Customer]);
    $customerB = User::factory()->create(['account_type' => AccountType::Customer]);

    $addressB = ShippingAddress::create([
        'user_id' => $customerB->id,
        'name' => 'Customer B',
        'email' => 'customerb@example.com',
        'phone' => '+15559998888',
        'address' => '789 Pine Rd',
        'city' => 'Gotham',
        'state' => 'NJ',
        'postal_code' => '07001',
        'country' => 'US',
        'is_default' => true,
    ]);

    Sanctum::actingAs($customerA, ['*']);

    $this->getJson("/api/v1/addresses/{$addressB->id}")->assertNotFound();
    $this->putJson("/api/v1/addresses/{$addressB->id}", [
        'name' => 'Hacked Name',
    ])->assertNotFound();
    $this->deleteJson("/api/v1/addresses/{$addressB->id}")->assertNotFound();
});

test('IDOR protection prevents customer from modifying or deleting another customers review', function () {
    $customerA = User::factory()->create(['account_type' => AccountType::Customer]);
    $customerB = User::factory()->create(['account_type' => AccountType::Customer]);

    $category = Category::create([
        'name' => 'Shoes',
        'slug' => 'shoes',
        'is_active' => true,
    ]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Runner',
        'slug' => 'runner',
        'regular_price' => 120.00,
        'is_active' => true,
    ]);

    $reviewB = ProductReview::create([
        'product_id' => $product->id,
        'user_id' => $customerB->id,
        'rating' => 5,
        'title' => 'Great Shoes',
        'comment' => 'Very comfortable sneakers.',
        'status' => ReviewStatus::Approved,
    ]);

    Sanctum::actingAs($customerA, ['*']);

    $this->putJson("/api/v1/reviews/{$reviewB->id}", [
        'rating' => 1,
        'comment' => 'Sabotaged review comment.',
    ])->assertStatus(403);

    $this->deleteJson("/api/v1/reviews/{$reviewB->id}")->assertStatus(403);
});

test('login returns generic error message without disclosing account existence', function () {
    $user = User::factory()->create([
        'email' => 'realcustomer@example.com',
        'password' => Hash::make('CorrectPassword123!'),
        'account_type' => AccountType::Customer,
    ]);

    // Wrong password for existing user
    $responseWrongPassword = $this->postJson('/api/v1/login', [
        'email' => 'realcustomer@example.com',
        'password' => 'WrongPassword123!',
    ]);

    // Non-existent user
    $responseNonExistent = $this->postJson('/api/v1/login', [
        'email' => 'fakecustomer@example.com',
        'password' => 'AnyPassword123!',
    ]);

    $responseWrongPassword->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);

    $responseNonExistent->assertStatus(401)
        ->assertJson([
            'success' => false,
            'message' => 'The provided credentials are incorrect.',
        ]);
});

test('login throttles after five consecutive failed attempts', function () {
    RateLimiter::clear('login:127.0.0.1');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', [
            'email' => 'victim@example.com',
            'password' => 'WrongPassword!',
        ])->assertStatus(401);
    }

    // 6th attempt should be throttled
    $response = $this->postJson('/api/v1/login', [
        'email' => 'victim@example.com',
        'password' => 'WrongPassword!',
    ]);

    expect($response->status())->toBeIn([422, 429]);
    expect($response->json('errors.email.0'))->toContain('Too many login attempts');
});

test('sensitive authentication data is never leaked in API responses', function () {
    $user = User::factory()->create([
        'name' => 'Secret User',
        'email' => 'secret@example.com',
        'password' => Hash::make('SuperSecretPassword123!'),
        'account_type' => AccountType::Customer,
    ]);

    Sanctum::actingAs($user, ['*']);

    $response = $this->getJson('/api/v1/profile');

    $response->assertOk();
    $content = $response->getContent();

    expect($content)
        ->not->toContain('SuperSecretPassword123!')
        ->not->toContain('$2y$') // Bcrypt hash header
        ->not->toContain('remember_token');
});
