<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateProduct
{
    /**
     * @param  array{
     *     name: string,
     *     subtitle?: string|null,
     *     slug?: string|null,
     *     description?: string|null,
     *     specifications?: list<array{label: string, value: string}>|null,
     *     show_details?: bool,
     *     show_specifications?: bool,
     *     status: string|ProductStatus,
     *     price_amount: int,
     *     currency: string,
     *     image_path?: string|null,
     *     category_id?: int|null
     * }  $data
     */
    public function handle(array $data): Product
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

        return Product::query()->create([
            'name' => $data['name'],
            'subtitle' => $data['subtitle'] ?? null,
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'specifications' => $this->normalizeSpecifications($data['specifications'] ?? null),
            'show_details' => $data['show_details'] ?? true,
            'show_specifications' => $data['show_specifications'] ?? true,
            'status' => $status,
            'price_amount' => $data['price_amount'],
            'currency' => strtoupper($data['currency']),
            'image_path' => $data['image_path'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ]);
    }

    /**
     * @param  list<array{label?: mixed, value?: mixed}>|null  $rows
     * @return list<array{label: string, value: string}>|null
     */
    private function normalizeSpecifications(?array $rows): ?array
    {
        if ($rows === null) {
            return null;
        }

        $normalized = [];
        foreach ($rows as $row) {
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            $value = isset($row['value']) ? trim((string) $row['value']) : '';
            if ($label === '' || $value === '') {
                continue;
            }
            $normalized[] = ['label' => $label, 'value' => $value];
        }

        return $normalized === [] ? null : $normalized;
    }
}
