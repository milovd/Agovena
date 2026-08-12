<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class DeleteProduct
{
    public function handle(Product $product): void
    {
        if ($this->isReferencedByOrders($product)) {
            throw ValidationException::withMessages([
                'product' => __('admin.products.validation.referenced'),
            ]);
        }

        DB::transaction(function () use ($product): void {
            $paths = $product->images()->pluck('path')->all();
            if (filled($product->image_path)) {
                $paths[] = $product->image_path;
            }

            ProductImage::query()->where('product_id', $product->id)->delete();
            $product->delete();

            foreach (array_unique(array_filter($paths)) as $path) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    public function isReferencedByOrders(Product $product): bool
    {
        return OrderItem::query()->where('product_id', $product->id)->exists();
    }
}
