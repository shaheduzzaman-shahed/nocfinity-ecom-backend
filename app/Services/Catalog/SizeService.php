<?php

namespace App\Services\Catalog;

use App\Models\Size;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SizeService
{
    /**
     * @return Collection<int, Size>
     */
    public function list(): Collection
    {
        return Size::query()
            ->withCount('productVariants')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Size
    {
        return Size::create([
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
        ])->loadCount('productVariants');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Size $size, array $data): Size
    {
        $size->update(Arr::only($data, ['name', 'sort_order']));

        return $size->loadCount('productVariants');
    }

    public function delete(Size $size): void
    {
        if ($size->productVariants()->exists()) {
            throw new ConflictHttpException(
                'This size is used by product variants and cannot be deleted.',
            );
        }

        $size->delete();
    }
}
