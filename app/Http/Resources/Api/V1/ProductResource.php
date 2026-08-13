<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Support\MoneyFormatter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $product->loadMissing(['category', 'images', 'capabilities', 'purchaseOptions.choices']);

        $images = $product->images->map(static fn ($image): string => Storage::disk('public')->url($image->path))->values();
        if ($images->isEmpty() && is_string($product->image_path) && $product->image_path !== '') {
            $images = collect([Storage::disk('public')->url($product->image_path)]);
        }

        return [
            'id' => $product->id,
            'name' => $product->name,
            'subtitle' => $product->subtitle,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'description' => $product->description,
            'price' => [
                'amount' => $product->price_amount,
                'currency' => $product->currency,
                'formatted' => MoneyFormatter::format($product->price_amount, $product->currency),
            ],
            'category' => $product->category === null ? null : [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'slug' => $product->category->slug,
            ],
            'images' => $images->all(),
            'capabilities' => $product->capabilities->pluck('capability')->values()->all(),
            'options' => $product->purchaseOptions->where('is_active', true)->values()->map(static function ($option): array {
                return [
                    'key' => $option->key,
                    'label' => $option->label,
                    'type' => $option->type->value,
                    'required' => $option->is_required,
                    'choices' => $option->choices->where('is_active', true)->values()->map(static fn ($choice): array => [
                        'value' => $choice->value,
                        'label' => $choice->label,
                    ])->all(),
                ];
            })->all(),
        ];
    }
}
