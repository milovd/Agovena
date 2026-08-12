<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteCategory
{
    public function handle(Category $category): void
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('admin.categories.validation.has_products'),
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => __('admin.categories.validation.has_children'),
            ]);
        }

        if (filled($category->image_path)) {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->delete();
    }
}
