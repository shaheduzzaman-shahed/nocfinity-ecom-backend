<?php

namespace Database\Factories;

use App\Models\Size;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Size>
 */
class SizeFactory extends Factory
{
    protected $model = Size::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'SIZE-'.Str::upper(Str::random(5)),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
