<?php

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class ProductImageService
{
    public const DIRECTORY = 'products';

    public const MAX_IMAGES = 8;

    /**
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, mixed>  $data
     * @return Collection<int, ProductImage>
     */
    public function addImages(Product $product, array $files, array $data): Collection
    {
        $files = array_values(array_filter(
            $files,
            fn ($file) => $file instanceof UploadedFile,
        ));

        if ($files === []) {
            throw ValidationException::withMessages([
                'images' => 'Please choose at least one image to upload.',
            ]);
        }

        $existingCount = $product->images()->count();

        if ($existingCount + count($files) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => 'A product can have at most '.self::MAX_IMAGES.' images.',
            ]);
        }

        $primaryIndex = isset($data['primary_index'])
            ? (int) $data['primary_index']
            : null;

        if ($primaryIndex === null && ($data['is_primary'] ?? false)) {
            $primaryIndex = 0;
        }

        $sortStart = array_key_exists('sort_order', $data)
            ? (int) $data['sort_order']
            : ((int) ($product->images()->max('sort_order') ?? -1) + 1);

        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $product,
                $files,
                $primaryIndex,
                $sortStart,
                &$storedPaths,
            ): void {
                foreach ($files as $index => $file) {
                    $path = $file->store(self::DIRECTORY, 'public');

                    if (! $path) {
                        throw new RuntimeException('The image could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $isPrimary = $primaryIndex !== null && $primaryIndex === $index;

                    if ($isPrimary) {
                        $product->images()->update(['is_primary' => false]);
                    }

                    $product->images()->create([
                        'image_url' => $path,
                        'is_primary' => $isPrimary,
                        'sort_order' => $sortStart + $index,
                    ]);
                }

                if (! $product->images()->where('is_primary', true)->exists()) {
                    $product->images()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->first()
                        ?->update(['is_primary' => true]);
                }
            });
        } catch (Throwable $exception) {
            $this->deleteFiles($storedPaths);

            throw $exception;
        }

        return $this->orderedImages($product);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateImage(Product $product, ProductImage $image, array $data): ProductImage
    {
        $this->ensureOwnership($product, $image);

        if (array_key_exists('is_primary', $data)) {
            if ($data['is_primary']) {
                DB::transaction(function () use ($product, $image): void {
                    $product->images()->update(['is_primary' => false]);
                    $image->update(['is_primary' => true]);
                });
            } elseif ($image->is_primary) {
                throw ValidationException::withMessages([
                    'is_primary' => 'Choose another primary image before unsetting this one.',
                ]);
            }
        }

        if (array_key_exists('sort_order', $data)) {
            $image->update(['sort_order' => (int) $data['sort_order']]);
        }

        return $image->fresh();
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        $this->ensureOwnership($product, $image);

        $path = $image->image_url;
        $wasPrimary = $image->is_primary;

        DB::transaction(function () use ($product, $image, $wasPrimary): void {
            $image->delete();

            if ($wasPrimary) {
                $product->images()
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->first()
                    ?->update(['is_primary' => true]);
            }
        });

        $this->deleteFile($path);
    }

    /**
     * @param  array<int, string>  $paths
     */
    public function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            $this->deleteFile($path);
        }
    }

    /**
     * @return Collection<int, ProductImage>
     */
    public function orderedImages(Product $product): Collection
    {
        return $product->images()
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function ensureOwnership(Product $product, ProductImage $image): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            throw new NotFoundHttpException('Product image not found.');
        }
    }

    private function deleteFile(?string $path): void
    {
        if (! $path) {
            return;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
