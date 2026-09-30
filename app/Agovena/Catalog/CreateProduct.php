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
     *     sku?: string|null,
     *     description?: string|null,
     *     specifications?: list<array{label: string, value: string}>|null,
     *     show_details?: bool,
     *     show_specifications?: bool,
     *     show_delivery_card?: bool|null,
     *     delivery_title?: string|null,
     *     delivery_text?: string|null,
     *     show_returns_card?: bool|null,
     *     returns_title?: string|null,
     *     returns_text?: string|null,
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

        $sku = isset($data['sku']) ? trim((string) $data['sku']) : null;
        $sku = $sku === '' ? null : $sku;

        return Product::query()->create([
            'name' => $data['name'],
            'subtitle' => $data['subtitle'] ?? null,
            'slug' => $slug,
            'sku' => $sku,
            'description' => $data['description'] ?? null,
            'specifications' => $this->normalizeSpecifications($data['specifications'] ?? null),
            'show_details' => $data['show_details'] ?? true,
            'show_specifications' => $data['show_specifications'] ?? true,
            'show_delivery_card' => $data['show_delivery_card'] ?? null,
            'delivery_title' => $this->nullableText($data['delivery_title'] ?? null),
            'delivery_text' => $this->nullableText($data['delivery_text'] ?? null),
            'show_returns_card' => $data['show_returns_card'] ?? null,
            'returns_title' => $this->nullableText($data['returns_title'] ?? null),
            'returns_text' => $this->nullableText($data['returns_text'] ?? null),
            'status' => $status,
            'price_amount' => $data['price_amount'],
            'currency' => strtoupper($data['currency']),
            'image_path' => $data['image_path'] ?? null,
            'category_id' => $data['category_id'] ?? null,
        ]);
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
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
