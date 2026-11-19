<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Men',
                'description' => 'Men\'s fashion and apparel',
                'children' => [
                    ['name' => 'T-Shirts', 'description' => 'Men\'s t-shirts and casual tops'],
                    ['name' => 'Shirts', 'description' => 'Men\'s formal and casual shirts'],
                    ['name' => 'Pants', 'description' => 'Men\'s trousers and jeans'],
                    ['name' => 'Jackets', 'description' => 'Men\'s jackets and outerwear'],
                ],
            ],
            [
                'name' => 'Women',
                'description' => 'Women\'s fashion and apparel',
                'children' => [
                    ['name' => 'Dresses', 'description' => 'Women\'s dresses for all occasions'],
                    ['name' => 'Tops', 'description' => 'Women\'s blouses, t-shirts, and tops'],
                    ['name' => 'Skirts', 'description' => 'Women\'s skirts and culottes'],
                    ['name' => 'Pants', 'description' => 'Women\'s trousers and jeans'],
                ],
            ],
            [
                'name' => 'Accessories',
                'description' => 'Fashion accessories for everyone',
                'children' => [
                    ['name' => 'Bags', 'description' => 'Handbags, backpacks, and totes'],
                    ['name' => 'Hats', 'description' => 'Caps, beanies, and sun hats'],
                    ['name' => 'Scarves', 'description' => 'Scarves and wraps'],
                ],
            ],
        ];

        $sortOrder = 0;

        foreach ($categories as $parentData) {
            $children = $parentData['children'] ?? [];
            unset($parentData['children']);

            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($parentData['name'])],
                [
                    ...$parentData,
                    'slug' => Str::slug($parentData['name']),
                    'is_active' => true,
                    'sort_order' => $sortOrder++,
                ],
            );

            $childSort = 0;
            foreach ($children as $childData) {
                Category::firstOrCreate(
                    ['slug' => Str::slug($parent->name.' '.$childData['name'])],
                    [
                        ...$childData,
                        'slug' => Str::slug($parent->name.' '.$childData['name']),
                        'parent_id' => $parent->id,
                        'is_active' => true,
                        'sort_order' => $childSort++,
                    ],
                );
            }
        }
    }
}
