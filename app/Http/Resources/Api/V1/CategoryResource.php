<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Category
 */
final class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Category $category */
        $category = $this->resource;

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'image' => is_string($category->image_path) && $category->image_path !== ''
                ? Storage::disk('public')->url($category->image_path)
                : null,
            'products_count' => (int) ($category->products_count ?? 0),
            'children' => $category->relationLoaded('children')
                ? $category->children->map(static fn (Category $child): array => [
                    'id' => $child->id,
                    'name' => $child->name,
                    'slug' => $child->slug,
                    'products_count' => (int) ($child->products_count ?? 0),
                ])->values()->all()
                : [],
        ];
    }
}
