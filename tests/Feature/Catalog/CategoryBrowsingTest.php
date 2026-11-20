<?php

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the public category listing only contains active categories in a tree', function () {
    $men = Category::factory()->create(['name' => 'Men', 'slug' => 'men', 'sort_order' => 1]);
    $women = Category::factory()->create(['name' => 'Women', 'slug' => 'women', 'sort_order' => 0]);
    Category::factory()->childOf($men)->create(['name' => 'T-Shirts', 'slug' => 'men-t-shirts']);
    Category::factory()->childOf($men)->inactive()->create(['name' => 'Old T-Shirts', 'slug' => 'old-t-shirts']);

    $archived = Category::factory()->inactive()->create(['name' => 'Archived', 'slug' => 'archived', 'sort_order' => 2]);
    Category::factory()->childOf($archived)->create(['name' => 'Hidden Child', 'slug' => 'hidden-child']);

    $response = $this->getJson('/api/v1/categories');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
        ]);

    $data = $response->json('data');

    expect($data)->toHaveCount(2)
        ->and($data[0]['name'])->toBe('Women')
        ->and($data[0]['children'])->toBe([])
        ->and($data[1]['name'])->toBe('Men')
        ->and(collect($data[1]['children'])->pluck('name')->all())->toBe(['T-Shirts']);
});

test('the public category listing is ordered by sort order and name', function () {
    Category::factory()->create(['name' => 'Zeta', 'slug' => 'zeta', 'sort_order' => 2]);
    Category::factory()->create(['name' => 'Alpha', 'slug' => 'alpha', 'sort_order' => 2]);
    Category::factory()->create(['name' => 'First', 'slug' => 'first', 'sort_order' => 1]);

    $names = collect($this->getJson('/api/v1/categories')->json('data'))->pluck('name')->all();

    expect($names)->toBe(['First', 'Alpha', 'Zeta']);
});

test('an active category details endpoint returns its active subtree and parent', function () {
    $men = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
    $shirts = Category::factory()->childOf($men)->create(['name' => 'Shirts', 'slug' => 'men-shirts']);
    Category::factory()->childOf($shirts)->create(['name' => 'Oxford', 'slug' => 'men-shirts-oxford']);
    Category::factory()->childOf($shirts)->inactive()->create(['name' => 'Retired', 'slug' => 'retired']);

    $response = $this->getJson("/api/v1/categories/{$men->id}");

    $response->assertOk()
        ->assertJsonPath('data.name', 'Men')
        ->assertJsonPath('data.parent', null)
        ->assertJsonPath('data.children.0.name', 'Shirts')
        ->assertJsonPath('data.children.0.children.0.name', 'Oxford');

    $this->getJson("/api/v1/categories/{$shirts->id}")
        ->assertOk()
        ->assertJsonPath('data.parent.id', $men->id)
        ->assertJsonPath('data.children.0.name', 'Oxford');
});

test('inactive categories are not publicly visible', function () {
    $inactive = Category::factory()->inactive()->create(['name' => 'Archived', 'slug' => 'archived']);

    $this->getJson("/api/v1/categories/{$inactive->id}")
        ->assertStatus(404)
        ->assertJson(['success' => false]);
});

test('active categories under an inactive parent are not publicly visible', function () {
    $inactiveParent = Category::factory()->inactive()->create(['name' => 'Archived', 'slug' => 'archived']);
    $child = Category::factory()->childOf($inactiveParent)->create(['name' => 'Child', 'slug' => 'child']);

    $this->getJson("/api/v1/categories/{$child->id}")->assertStatus(404);
});

test('missing public categories return 404', function () {
    $this->getJson('/api/v1/categories/999')->assertStatus(404);
});

test('the public category endpoints do not require authentication', function () {
    Category::factory()->create(['name' => 'Men', 'slug' => 'men']);

    $this->getJson('/api/v1/categories')->assertOk();
});
