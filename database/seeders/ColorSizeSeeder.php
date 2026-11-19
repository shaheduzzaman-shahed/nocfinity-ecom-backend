<?php

namespace Database\Seeders;

use App\Models\Color;
use App\Models\Size;
use Illuminate\Database\Seeder;

class ColorSizeSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            ['name' => 'Black', 'hex_code' => '#000000'],
            ['name' => 'White', 'hex_code' => '#FFFFFF'],
            ['name' => 'Navy', 'hex_code' => '#001F3F'],
            ['name' => 'Red', 'hex_code' => '#E74C3C'],
            ['name' => 'Grey', 'hex_code' => '#95A5A6'],
            ['name' => 'Beige', 'hex_code' => '#F5F5DC'],
            ['name' => 'Olive', 'hex_code' => '#556B2F'],
            ['name' => 'Blue', 'hex_code' => '#3498DB'],
        ];

        foreach ($colors as $color) {
            Color::firstOrCreate(['name' => $color['name']], $color);
        }

        $sizes = [
            ['name' => 'XS', 'sort_order' => 0],
            ['name' => 'S', 'sort_order' => 1],
            ['name' => 'M', 'sort_order' => 2],
            ['name' => 'L', 'sort_order' => 3],
            ['name' => 'XL', 'sort_order' => 4],
            ['name' => 'XXL', 'sort_order' => 5],
        ];

        foreach ($sizes as $size) {
            Size::firstOrCreate(['name' => $size['name']], $size);
        }
    }
}
