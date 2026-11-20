<?php

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $permissions
 */
function actingAsImageStaff(string ...$permissions): User
{
    $user = staffWithPermissions($permissions);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

test('staff with products.edit can upload multiple product images', function () {
    actingAsImageStaff('products.edit');
    Storage::fake('public');

    $product = Product::factory()->create();

    $response = $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        [
            'images' => [
                UploadedFile::fake()->image('one.jpg', 800, 800),
                UploadedFile::fake()->image('two.png', 800, 800),
            ],
            'primary_index' => 1,
        ],
        ['Accept' => 'application/json'],
    );

    $response->assertCreated()
        ->assertJsonPath('message', 'Product images uploaded successfully.');

    $images = $response->json('data');

    expect($images)->toHaveCount(2)
        ->and($images[0]['is_primary'])->toBeTrue()
        ->and($images[1]['is_primary'])->toBeFalse();

    expect(ProductImage::where('product_id', $product->id)->count())->toBe(2);

    foreach (ProductImage::where('product_id', $product->id)->pluck('image_url') as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

test('the first uploaded image becomes primary when none is chosen', function () {
    actingAsImageStaff('products.edit');
    Storage::fake('public');

    $product = Product::factory()->create();

    $response = $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        ['images' => [UploadedFile::fake()->image('one.jpg')]],
        ['Accept' => 'application/json'],
    );

    $response->assertCreated()->assertJsonPath('data.0.is_primary', true);
});

test('product image uploads validate files and limits', function () {
    actingAsImageStaff('products.edit');
    Storage::fake('public');

    $product = Product::factory()->create();

    $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        ['images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')]],
        ['Accept' => 'application/json'],
    )->assertStatus(422)->assertJsonValidationErrors(['images.0']);

    $tooMany = array_map(
        fn (int $index) => UploadedFile::fake()->image("image-{$index}.jpg"),
        range(1, 9),
    );

    $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        ['images' => $tooMany],
        ['Accept' => 'application/json'],
    )->assertStatus(422)->assertJsonValidationErrors(['images']);

    $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        [],
        ['Accept' => 'application/json'],
    )->assertStatus(422)->assertJsonValidationErrors(['images']);
});

test('a product cannot hold more than eight images', function () {
    actingAsImageStaff('products.edit');
    Storage::fake('public');

    $product = Product::factory()->create();

    foreach (range(1, 8) as $index) {
        ProductImage::factory()->for($product)->create([
            'image_url' => "products/existing-{$index}.jpg",
            'sort_order' => $index,
        ]);
    }

    $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        ['images' => [UploadedFile::fake()->image('extra.jpg')]],
        ['Accept' => 'application/json'],
    )->assertStatus(422)->assertJsonValidationErrors(['images']);
});

test('an image can be promoted to primary and reordered', function () {
    actingAsImageStaff('products.edit');
    Storage::fake('public');

    $product = Product::factory()->create();
    $primary = ProductImage::factory()->for($product)->primary()->create([
        'sort_order' => 0,
    ]);
    $secondary = ProductImage::factory()->for($product)->create([
        'sort_order' => 1,
    ]);

    $this->putJson(
        "/api/v1/admin/products/{$product->id}/images/{$secondary->id}",
        ['is_primary' => true, 'sort_order' => 5],
    )->assertOk()
        ->assertJsonPath('data.is_primary', true)
        ->assertJsonPath('data.sort_order', 5);

    $this->assertDatabaseHas('product_images', [
        'id' => $primary->id,
        'is_primary' => false,
    ]);
    $this->assertDatabaseHas('product_images', [
        'id' => $secondary->id,
        'is_primary' => true,
        'sort_order' => 5,
    ]);
});

test('the only primary image cannot be unset', function () {
    actingAsImageStaff('products.edit');

    $product = Product::factory()->create();
    $image = ProductImage::factory()->for($product)->primary()->create();

    $this->putJson(
        "/api/v1/admin/products/{$product->id}/images/{$image->id}",
        ['is_primary' => false],
    )->assertStatus(422)->assertJsonValidationErrors(['is_primary']);
});

test('deleting the primary image promotes the next image', function () {
    actingAsImageStaff('products.edit');
    Storage::fake('public');

    $product = Product::factory()->create();
    $path = 'products/primary.jpg';
    Storage::disk('public')->put($path, 'image-data');

    $primary = ProductImage::factory()->for($product)->primary()->create([
        'image_url' => $path,
        'sort_order' => 0,
    ]);
    $next = ProductImage::factory()->for($product)->create([
        'image_url' => 'products/next.jpg',
        'sort_order' => 1,
    ]);

    $this->deleteJson(
        "/api/v1/admin/products/{$product->id}/images/{$primary->id}",
    )->assertOk();

    $this->assertDatabaseMissing('product_images', ['id' => $primary->id]);
    Storage::disk('public')->assertMissing($path);
    $this->assertDatabaseHas('product_images', [
        'id' => $next->id,
        'is_primary' => true,
    ]);
});

test('images must belong to the product in the route', function () {
    actingAsImageStaff('products.edit');

    $product = Product::factory()->create();
    $otherProduct = Product::factory()->create();
    $otherImage = ProductImage::factory()->for($otherProduct)->create();

    $this->putJson(
        "/api/v1/admin/products/{$product->id}/images/{$otherImage->id}",
        ['sort_order' => 3],
    )->assertStatus(404);

    $this->deleteJson(
        "/api/v1/admin/products/{$product->id}/images/{$otherImage->id}",
    )->assertStatus(404);
});

test('image management requires products.edit', function () {
    actingAsImageStaff('products.view');
    Storage::fake('public');

    $product = Product::factory()->create();

    $this->post(
        "/api/v1/admin/products/{$product->id}/images",
        ['images' => [UploadedFile::fake()->image('one.jpg')]],
        ['Accept' => 'application/json'],
    )->assertStatus(403);
});
