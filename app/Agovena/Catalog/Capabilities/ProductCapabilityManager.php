<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Capabilities;

use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductCapabilityManager
{
    public function __construct(
        private readonly ProductCapabilityRegistry $registry,
    ) {}

    public function enable(Product $product, string $capability, array $config = []): ProductCapability
    {
        $definition = $this->registry->get($capability);
        if ($definition === null) {
            throw ValidationException::withMessages([
                'capability' => __('admin.products.capabilities.unknown', ['capability' => $capability]),
            ]);
        }

        foreach ($definition->requires as $required) {
            if (! $product->hasCapability($required)) {
                throw ValidationException::withMessages([
                    'capability' => __('admin.products.capabilities.missing_requirement', [
                        'capability' => $capability,
                        'requires' => $required,
                    ]),
                ]);
            }
        }

        return DB::transaction(function () use ($product, $capability, $config): ProductCapability {
            return ProductCapability::query()->updateOrCreate(
                [
                    'product_id' => $product->id,
                    'capability' => $capability,
                ],
                [
                    'config' => $config === [] ? null : $config,
                ],
            );
        });
    }

    public function disable(Product $product, string $capability): void
    {
        ProductCapability::query()
            ->where('product_id', $product->id)
            ->where('capability', $capability)
            ->delete();
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    public function syncConfig(Product $product, string $capability, ?array $config): void
    {
        $row = ProductCapability::query()
            ->where('product_id', $product->id)
            ->where('capability', $capability)
            ->first();

        if ($row === null) {
            return;
        }

        $row->config = $config;
        $row->save();
    }
}
