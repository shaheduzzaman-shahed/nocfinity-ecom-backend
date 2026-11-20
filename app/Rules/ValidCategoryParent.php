<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCategoryParent implements ValidationRule
{
    public function __construct(private readonly ?Category $category = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $parent = Category::find($value);

        if (! $parent) {
            // The exists rule already reports unknown categories.
            return;
        }

        if ($this->category?->is($parent)) {
            $fail('A category cannot be its own parent.');

            return;
        }

        if (! $this->category) {
            return;
        }

        $ancestor = $parent->parent;

        while ($ancestor) {
            if ($ancestor->is($this->category)) {
                $fail('A category cannot be nested under one of its own descendants.');

                return;
            }

            $ancestor = $ancestor->parent;
        }
    }
}
