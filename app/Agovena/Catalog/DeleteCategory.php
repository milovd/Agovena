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
                'category' => 'This category still has products. Move or reassign them first, or set the category inactive.',
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category has subcategories. Remove or reassign them first.',
            ]);
        }

        if (filled($category->image_path)) {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->delete();
    }
}
