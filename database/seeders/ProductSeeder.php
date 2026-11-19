<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $colors = Color::pluck('id', 'name');
        $sizes = Size::pluck('id', 'name');

        $products = [
            ['category' => 'men-t-shirts', 'name' => 'Classic Cotton Crew Neck', 'price' => 29.99, 'discount' => 24.99, 'colors' => ['Black', 'White', 'Navy'], 'sizes' => ['S', 'M', 'L', 'XL']],
            ['category' => 'men-t-shirts', 'name' => 'Relaxed Fit Graphic Tee', 'price' => 34.99, 'colors' => ['Black', 'Grey', 'White'], 'sizes' => ['M', 'L', 'XL']],
            ['category' => 'men-t-shirts', 'name' => 'Premium V-Neck Tee', 'price' => 39.99, 'colors' => ['Navy', 'Olive', 'Beige'], 'sizes' => ['S', 'M', 'L', 'XL', 'XXL']],
            ['category' => 'men-shirts', 'name' => 'Oxford Button-Down Shirt', 'price' => 59.99, 'colors' => ['White', 'Blue', 'Grey'], 'sizes' => ['S', 'M', 'L', 'XL']],
            ['category' => 'men-shirts', 'name' => 'Linen Summer Shirt', 'price' => 64.99, 'discount' => 54.99, 'colors' => ['White', 'Beige', 'Navy'], 'sizes' => ['M', 'L', 'XL']],
            ['category' => 'men-pants', 'name' => 'Slim Fit Chinos', 'price' => 49.99, 'colors' => ['Beige', 'Navy', 'Olive'], 'sizes' => ['S', 'M', 'L', 'XL']],
            ['category' => 'men-pants', 'name' => 'Classic Straight Jeans', 'price' => 69.99, 'colors' => ['Blue', 'Black'], 'sizes' => ['S', 'M', 'L', 'XL', 'XXL']],
            ['category' => 'men-jackets', 'name' => 'Lightweight Bomber Jacket', 'price' => 89.99, 'discount' => 74.99, 'colors' => ['Black', 'Navy', 'Olive'], 'sizes' => ['M', 'L', 'XL']],
            ['category' => 'men-jackets', 'name' => 'Wool Blend Overcoat', 'price' => 149.99, 'colors' => ['Black', 'Grey'], 'sizes' => ['M', 'L', 'XL']],
            ['category' => 'women-dresses', 'name' => 'Floral Midi Dress', 'price' => 79.99, 'discount' => 64.99, 'colors' => ['Red', 'Navy', 'Beige'], 'sizes' => ['XS', 'S', 'M', 'L']],
            ['category' => 'women-dresses', 'name' => 'Little Black Dress', 'price' => 89.99, 'colors' => ['Black'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
            ['category' => 'women-dresses', 'name' => 'Summer Maxi Dress', 'price' => 74.99, 'colors' => ['White', 'Blue', 'Beige'], 'sizes' => ['S', 'M', 'L']],
            ['category' => 'women-tops', 'name' => 'Silk Blend Blouse', 'price' => 54.99, 'colors' => ['White', 'Beige', 'Navy'], 'sizes' => ['XS', 'S', 'M', 'L']],
            ['category' => 'women-tops', 'name' => 'Ribbed Knit Top', 'price' => 39.99, 'discount' => 29.99, 'colors' => ['Black', 'Grey', 'Red'], 'sizes' => ['XS', 'S', 'M', 'L', 'XL']],
            ['category' => 'women-skirts', 'name' => 'A-Line Midi Skirt', 'price' => 49.99, 'colors' => ['Black', 'Navy', 'Beige'], 'sizes' => ['XS', 'S', 'M', 'L']],
            ['category' => 'women-pants', 'name' => 'High-Waist Wide Leg Pants', 'price' => 59.99, 'colors' => ['Black', 'Beige', 'Olive'], 'sizes' => ['XS', 'S', 'M', 'L']],
            ['category' => 'women-pants', 'name' => 'Tailored Slim Trousers', 'price' => 64.99, 'discount' => 49.99, 'colors' => ['Black', 'Navy', 'Grey'], 'sizes' => ['S', 'M', 'L', 'XL']],
            ['category' => 'accessories-bags', 'name' => 'Leather Tote Bag', 'price' => 119.99, 'colors' => ['Black', 'Beige'], 'sizes' => ['M']],
            ['category' => 'accessories-bags', 'name' => 'Canvas Backpack', 'price' => 49.99, 'colors' => ['Navy', 'Olive', 'Grey'], 'sizes' => ['M']],
            ['category' => 'accessories-hats', 'name' => 'Classic Baseball Cap', 'price' => 24.99, 'colors' => ['Black', 'Navy', 'White'], 'sizes' => ['M']],
            ['category' => 'accessories-hats', 'name' => 'Wool Fedora Hat', 'price' => 44.99, 'colors' => ['Black', 'Grey', 'Beige'], 'sizes' => ['M', 'L']],
            ['category' => 'accessories-scarves', 'name' => 'Cashmere Blend Scarf', 'price' => 69.99, 'discount' => 54.99, 'colors' => ['Grey', 'Beige', 'Red'], 'sizes' => ['M']],
        ];

        $skuCounter = 1;

        foreach ($products as $productData) {
            $category = Category::where('slug', $productData['category'])->first();
            if (! $category) {
                continue;
            }

            $slug = Str::slug($productData['name']);

            $product = Product::firstOrCreate(
                ['slug' => $slug],
                [
                    'category_id' => $category->id,
                    'name' => $productData['name'],
                    'slug' => $slug,
                    'description' => "Premium quality {$productData['name']}. Crafted with attention to detail for the modern fashion-conscious individual.",
                    'regular_price' => $productData['price'],
                    'discount_price' => $productData['discount'] ?? null,
                    'is_active' => true,
                ],
            );

            ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'is_primary' => true],
                [
                    'product_id' => $product->id,
                    'image_url' => "products/{$slug}-1.jpg",
                    'is_primary' => true,
                    'sort_order' => 0,
                ],
            );

            ProductImage::firstOrCreate(
                ['product_id' => $product->id, 'sort_order' => 1],
                [
                    'product_id' => $product->id,
                    'image_url' => "products/{$slug}-2.jpg",
                    'is_primary' => false,
                    'sort_order' => 1,
                ],
            );

            foreach ($productData['colors'] as $colorName) {
                foreach ($productData['sizes'] as $sizeName) {
                    $colorId = $colors[$colorName] ?? null;
                    $sizeId = $sizes[$sizeName] ?? null;
                    if (! $colorId || ! $sizeId) {
                        continue;
                    }

                    ProductVariant::firstOrCreate(
                        ['product_id' => $product->id, 'color_id' => $colorId, 'size_id' => $sizeId],
                        [
                            'product_id' => $product->id,
                            'color_id' => $colorId,
                            'size_id' => $sizeId,
                            'sku' => sprintf('NOC-%05d', $skuCounter++),
                            'stock' => rand(5, 50),
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }
}
