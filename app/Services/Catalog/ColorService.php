<?php

namespace App\Services\Catalog;

use App\Models\Color;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ColorService
{
    /**
     * @return Collection<int, Color>
     */
    public function list(): Collection
    {
        return Color::query()
            ->withCount('productVariants')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Color
    {
        return Color::create([
            'name' => $data['name'],
            'hex_code' => $this->normalizeHex($data['hex_code'] ?? null),
        ])->loadCount('productVariants');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Color $color, array $data): Color
    {
        $attributes = Arr::only($data, ['name', 'hex_code']);

        if (array_key_exists('hex_code', $attributes)) {
            $attributes['hex_code'] = $this->normalizeHex($attributes['hex_code']);
        }

        $color->update($attributes);

        return $color->loadCount('productVariants');
    }

    public function delete(Color $color): void
    {
        if ($color->productVariants()->exists()) {
            throw new ConflictHttpException(
                'This color is used by product variants and cannot be deleted.',
            );
        }

        $color->delete();
    }

    private function normalizeHex(?string $hex): ?string
    {
        return $hex ? Str::upper($hex) : null;
    }
}
