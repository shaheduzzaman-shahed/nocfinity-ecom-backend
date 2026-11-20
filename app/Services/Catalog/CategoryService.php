<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CategoryService
{
    private const IMAGE_DIRECTORY = 'categories';

    /**
     * Flat categories ordered for display, assembled into a parent-child tree.
     *
     * @return Collection<int, Category>
     */
    public function adminTree(): Collection
    {
        return $this->buildTree(
            Category::query()
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * Storefront tree containing active categories only. Children of inactive
     * parents are excluded with their parent.
     *
     * @return Collection<int, Category>
     */
    public function publicTree(): Collection
    {
        return $this->buildTree(
            Category::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        );
    }

    public function find(Category $category): Category
    {
        return $category->load([
            'parent',
            'children' => fn ($query) => $query->orderBy('sort_order')->orderBy('name'),
        ])->loadCount('products');
    }

    /**
     * Resolve an active category from the storefront tree, including its
     * active descendants.
     */
    public function findPublic(Category $category): Category
    {
        $match = $this->findInTree($this->publicTree(), $category->id);

        if (! $match) {
            throw new NotFoundHttpException('Category not found.');
        }

        return $match->load('parent');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Category
    {
        $imagePath = $this->storeImage($data['image'] ?? null);

        try {
            $category = DB::transaction(fn (): Category => Category::create([
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'slug' => $this->generateSlug($data['name']),
                'description' => $data['description'] ?? null,
                'image' => $imagePath,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $data['sort_order'] ?? 0,
            ]));
        } catch (Throwable $exception) {
            $this->deleteImage($imagePath);

            throw $exception;
        }

        return $category->load('parent')->loadCount('products');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $oldImage = $category->image;
        $newImage = null;

        if (($data['image'] ?? null) instanceof UploadedFile) {
            $newImage = $this->storeImage($data['image']);
        }

        $attributes = Arr::only($data, [
            'parent_id',
            'name',
            'description',
            'is_active',
            'sort_order',
        ]);

        if (array_key_exists('name', $attributes) && $attributes['name'] !== $category->name) {
            $attributes['slug'] = $this->generateSlug($attributes['name'], $category);
        }

        if ($newImage) {
            $attributes['image'] = $newImage;
        }

        try {
            $category->update($attributes);
        } catch (Throwable $exception) {
            $this->deleteImage($newImage);

            throw $exception;
        }

        if ($newImage) {
            $this->deleteImage($oldImage);
        }

        return $this->find($category->fresh());
    }

    public function delete(Category $category): void
    {
        if ($category->products()->exists()) {
            throw new ConflictHttpException(
                'This category contains products and cannot be deleted.',
            );
        }

        $imagePath = $category->image;

        $category->delete();

        $this->deleteImage($imagePath);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function buildTree(Collection $categories): Collection
    {
        $childrenByParent = $categories->groupBy(
            fn (Category $category) => $category->parent_id ?? 0,
        );

        $attachChildren = function (Category $category) use (&$attachChildren, $childrenByParent): Category {
            $children = $childrenByParent
                ->get($category->id, collect())
                ->map(fn (Category $child) => $attachChildren($child))
                ->values();

            $category->setRelation('children', $children);

            return $category;
        };

        return $categories
            ->filter(fn (Category $category) => $category->parent_id === null)
            ->map(fn (Category $root) => $attachChildren($root))
            ->values();
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function findInTree(Collection $categories, int $id): ?Category
    {
        foreach ($categories as $category) {
            if ($category->id === $id) {
                return $category;
            }

            $match = $this->findInTree($category->children, $id);

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function generateSlug(string $name, ?Category $ignore = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $suffix = 2;

        while (Category::query()
            ->where('slug', $slug)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function storeImage(mixed $image): ?string
    {
        if (! $image instanceof UploadedFile) {
            return null;
        }

        $path = $image->store(self::IMAGE_DIRECTORY, 'public');

        return $path === false ? null : $path;
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
