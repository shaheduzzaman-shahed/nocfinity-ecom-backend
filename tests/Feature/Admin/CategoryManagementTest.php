<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function actingAsCategoryStaff(string $permission): User
{
    $user = staffWithPermissions([$permission]);
    Sanctum::actingAs($user, ['admin']);

    return $user;
}

function makeCategoryProduct(Category $category): Product
{
    return Product::create([
        'category_id' => $category->id,
        'name' => 'Test Product',
        'slug' => 'test-product-'.Str::lower(Str::random(6)),
        'regular_price' => 19.99,
        'is_active' => true,
    ]);
}

test('staff with categories.view can browse the category tree', function () {
    actingAsCategoryStaff('categories.view');

    $men = Category::factory()->create(['name' => 'Men', 'slug' => 'men', 'sort_order' => 1]);
    $women = Category::factory()->create(['name' => 'Women', 'slug' => 'women', 'sort_order' => 0]);
    Category::factory()->create([
        'name' => 'T-Shirts',
        'slug' => 'men-t-shirts',
        'parent_id' => $men->id,
        'sort_order' => 2,
    ]);
    Category::factory()->inactive()->create(['name' => 'Archived', 'slug' => 'archived', 'sort_order' => 3]);
    makeCategoryProduct($men);

    $response = $this->getJson('/api/v1/admin/categories');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
        ]);

    $data = $response->json('data');

    expect($data)->toHaveCount(3)
        ->and($data[0]['name'])->toBe('Women')
        ->and($data[1]['name'])->toBe('Men')
        ->and($data[1]['children'][0]['name'])->toBe('T-Shirts')
        ->and($data[1]['products_count'])->toBe(1)
        ->and($data[2]['name'])->toBe('Archived')
        ->and($data[2]['is_active'])->toBeFalse();
});

test('listing categories requires the categories.view permission', function () {
    Sanctum::actingAs(staffWithPermissions(['products.view']), ['admin']);

    $this->getJson('/api/v1/admin/categories')->assertStatus(403);
});

test('customers cannot access admin category management', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/admin/categories')->assertStatus(403);
});

test('staff with categories.view can view a category with its parent and children', function () {
    actingAsCategoryStaff('categories.view');

    $parent = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'T-Shirts', 'slug' => 'men-t-shirts']);

    $response = $this->getJson("/api/v1/admin/categories/{$parent->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Men')
        ->assertJsonPath('data.parent', null)
        ->assertJsonPath('data.children.0.name', 'T-Shirts')
        ->assertJsonPath('data.products_count', 0);

    $this->getJson("/api/v1/admin/categories/{$child->id}")
        ->assertOk()
        ->assertJsonPath('data.parent.id', $parent->id)
        ->assertJsonPath('data.parent.name', 'Men');
});

test('missing categories return 404', function () {
    actingAsCategoryStaff('categories.view');

    $this->getJson('/api/v1/admin/categories/999')->assertStatus(404);
});

test('staff with categories.manage can create a category', function () {
    actingAsCategoryStaff('categories.manage');

    $parent = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);

    $response = $this->postJson('/api/v1/admin/categories', [
        'name' => "Men's Fashion",
        'description' => 'Menswear and accessories.',
        'parent_id' => $parent->id,
        'is_active' => false,
        'sort_order' => 4,
    ]);

    $response->assertCreated()
        ->assertJson([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => [
                'name' => "Men's Fashion",
                'slug' => 'mens-fashion',
                'description' => 'Menswear and accessories.',
                'is_active' => false,
                'sort_order' => 4,
            ],
        ])
        ->assertJsonPath('data.parent.id', $parent->id);

    $this->assertDatabaseHas('categories', [
        'slug' => 'mens-fashion',
        'parent_id' => $parent->id,
        'is_active' => 0,
        'sort_order' => 4,
    ]);
});

test('category slugs are generated from the name and kept unique', function () {
    actingAsCategoryStaff('categories.manage');

    $first = $this->postJson('/api/v1/admin/categories', ['name' => 'Men']);
    $second = $this->postJson('/api/v1/admin/categories', ['name' => 'Men']);
    $third = $this->postJson('/api/v1/admin/categories', ['name' => 'Men']);

    $first->assertCreated()->assertJsonPath('data.slug', 'men');
    $second->assertCreated()->assertJsonPath('data.slug', 'men-2');
    $third->assertCreated()->assertJsonPath('data.slug', 'men-3');
});

test('a category image can be uploaded', function () {
    Storage::fake('public');
    actingAsCategoryStaff('categories.manage');

    $response = $this->post(
        '/api/v1/admin/categories',
        [
            'name' => 'Bags',
            'image' => UploadedFile::fake()->image('bags.jpg', 800, 600),
        ],
        ['Accept' => 'application/json'],
    );

    $response->assertCreated();

    $files = Storage::disk('public')->allFiles('categories');

    expect($files)->toHaveCount(1)
        ->and($response->json('data.image'))->toBe(Storage::disk('public')->url($files[0]));
});

test('category creation validates its input', function () {
    actingAsCategoryStaff('categories.manage');

    $response = $this->post(
        '/api/v1/admin/categories',
        [
            'description' => 'Missing a name.',
            'parent_id' => 999,
            'image' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
        ],
        ['Accept' => 'application/json'],
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'parent_id', 'image']);
});

test('oversized category images are rejected', function () {
    actingAsCategoryStaff('categories.manage');

    $response = $this->post(
        '/api/v1/admin/categories',
        [
            'name' => 'Large Image',
            'image' => UploadedFile::fake()->image('large.jpg')->size(3000),
        ],
        ['Accept' => 'application/json'],
    );

    $response->assertStatus(422)->assertJsonValidationErrors(['image']);
});

test('a category cannot be its own parent', function () {
    actingAsCategoryStaff('categories.manage');

    $category = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);

    $this->putJson("/api/v1/admin/categories/{$category->id}", [
        'parent_id' => $category->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
});

test('a category cannot be nested under its own descendant', function () {
    actingAsCategoryStaff('categories.manage');

    $root = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
    $child = Category::factory()->childOf($root)->create(['name' => 'T-Shirts', 'slug' => 'men-t-shirts']);
    $grandchild = Category::factory()->childOf($child)->create(['name' => 'Basics', 'slug' => 'men-t-shirts-basics']);

    $this->putJson("/api/v1/admin/categories/{$root->id}", [
        'parent_id' => $grandchild->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['parent_id']);
});

test('staff with categories.manage can update a category', function () {
    actingAsCategoryStaff('categories.manage');

    $category = Category::factory()->create([
        'name' => 'Men',
        'slug' => 'men',
        'description' => 'Old description.',
        'sort_order' => 0,
    ]);

    $response = $this->putJson("/api/v1/admin/categories/{$category->id}", [
        'name' => 'Menswear',
        'description' => 'Updated description.',
        'is_active' => false,
        'sort_order' => 7,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Menswear')
        ->assertJsonPath('data.slug', 'menswear')
        ->assertJsonPath('data.description', 'Updated description.')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.sort_order', 7);

    $this->assertDatabaseHas('categories', [
        'id' => $category->id,
        'slug' => 'menswear',
        'is_active' => 0,
        'sort_order' => 7,
    ]);
});

test('updating other fields keeps the existing slug', function () {
    actingAsCategoryStaff('categories.manage');

    $category = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);

    $this->putJson("/api/v1/admin/categories/{$category->id}", [
        'description' => 'Only the description changes.',
    ])->assertOk()->assertJsonPath('data.slug', 'men');
});

test('a category can be moved to a different parent', function () {
    actingAsCategoryStaff('categories.manage');

    $men = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
    $women = Category::factory()->create(['name' => 'Women', 'slug' => 'women']);
    $child = Category::factory()->childOf($men)->create(['name' => 'T-Shirts', 'slug' => 'men-t-shirts']);

    $this->putJson("/api/v1/admin/categories/{$child->id}", [
        'parent_id' => $women->id,
    ])->assertOk()->assertJsonPath('data.parent.id', $women->id);

    $this->assertDatabaseHas('categories', [
        'id' => $child->id,
        'parent_id' => $women->id,
    ]);
});

test('updating a category image replaces the previous file', function () {
    Storage::fake('public');
    actingAsCategoryStaff('categories.manage');

    $this->post(
        '/api/v1/admin/categories',
        [
            'name' => 'Bags',
            'image' => UploadedFile::fake()->image('old.jpg'),
        ],
        ['Accept' => 'application/json'],
    )->assertCreated();

    $oldPath = Storage::disk('public')->allFiles('categories')[0];
    $category = Category::firstOrFail();

    $this->post(
        "/api/v1/admin/categories/{$category->id}",
        [
            '_method' => 'put',
            'name' => 'Bags',
            'image' => UploadedFile::fake()->image('new.jpg'),
        ],
        ['Accept' => 'application/json'],
    )->assertOk();

    $newFiles = Storage::disk('public')->allFiles('categories');

    Storage::disk('public')->assertMissing($oldPath);
    expect($newFiles)->toHaveCount(1)
        ->and($newFiles[0])->not->toBe($oldPath);
});

test('staff with categories.manage can delete a category', function () {
    actingAsCategoryStaff('categories.manage');

    $category = Category::factory()->create(['name' => 'Temporary', 'slug' => 'temporary']);

    $this->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertOk()
        ->assertJson(['success' => true, 'message' => 'Category deleted successfully.']);

    $this->assertDatabaseMissing('categories', ['id' => $category->id]);
});

test('deleting a category removes its stored image', function () {
    Storage::fake('public');
    actingAsCategoryStaff('categories.manage');

    $this->post(
        '/api/v1/admin/categories',
        [
            'name' => 'Accessories',
            'image' => UploadedFile::fake()->image('accessories.jpg'),
        ],
        ['Accept' => 'application/json'],
    )->assertCreated();

    $path = Storage::disk('public')->allFiles('categories')[0];
    $category = Category::firstOrFail();

    $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertOk();

    Storage::disk('public')->assertMissing($path);
});

test('deleting a parent category promotes its children to root categories', function () {
    actingAsCategoryStaff('categories.manage');

    $parent = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
    $child = Category::factory()->childOf($parent)->create(['name' => 'T-Shirts', 'slug' => 'men-t-shirts']);

    $this->deleteJson("/api/v1/admin/categories/{$parent->id}")->assertOk();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('a category containing products cannot be deleted', function () {
    actingAsCategoryStaff('categories.manage');

    $category = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
    makeCategoryProduct($category);

    $this->deleteJson("/api/v1/admin/categories/{$category->id}")
        ->assertStatus(409)
        ->assertJson([
            'success' => false,
            'message' => 'This category contains products and cannot be deleted.',
        ]);

    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('category mutations require the categories.manage permission', function () {
    actingAsCategoryStaff('categories.view');

    $category = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);

    $this->postJson('/api/v1/admin/categories', ['name' => 'Women'])->assertStatus(403);
    $this->putJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Menswear'])->assertStatus(403);
    $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertStatus(403);
});
