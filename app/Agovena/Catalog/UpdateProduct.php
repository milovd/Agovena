<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class UpdateProduct
{
    /**
     * @param  array{
     *     name: string,
     *     slug?: string|null,
     *     description?: string|null,
     *     status: string|ProductStatus,
     *     price_amount: int,
     *     currency: string,
     *     image_path?: string|null,
     *     category_id?: int|null
     * }  $data
     */
    public function handle(Product $product, array $data): Product
    {
        $status = $data['status'] instanceof ProductStatus
            ? $data['status']
            : ProductStatus::from($data['status']);

        if ($data['price_amount'] < 0) {
            throw ValidationException::withMessages([
                'price_amount' => 'Price cannot be negative.',
            ]);
        }

        $slug = filled($data['slug'] ?? null)
            ? Str::slug((string) $data['slug'])
            : Str::slug($data['name']);

        $product->fill([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'status' => $status,
            'price_amount' => $data['price_amount'],
            'currency' => strtoupper($data['currency']),
            'image_path' => $data['image_path'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ]);
        $product->save();

        return $product->refresh();
    }
}
