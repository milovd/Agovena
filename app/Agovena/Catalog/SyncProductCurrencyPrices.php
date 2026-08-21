<?php

declare(strict_types=1);

namespace App\Agovena\Catalog;

use App\Models\Product;
use App\Models\ProductCurrencyPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SyncProductCurrencyPrices
{
    /**
     * @param  array<string, int>  $pricesByCurrency  Uppercase currency => minor units (omit/empty to clear)
     */
    public function handle(Product $product, array $pricesByCurrency): void
    {
        $native = strtoupper($product->currency);

        DB::transaction(function () use ($product, $pricesByCurrency, $native): void {
            $keep = [];

            foreach ($pricesByCurrency as $code => $amount) {
                $code = strtoupper(trim((string) $code));
                if ($code === '' || $code === $native) {
                    continue;
                }

                if ($amount < 0) {
                    throw ValidationException::withMessages([
                        'currencyPrices.'.$code => __('admin.products.validation.amount_negative'),
                    ]);
                }

                ProductCurrencyPrice::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'currency' => $code,
                    ],
                    ['price_amount' => $amount],
                );
                $keep[] = $code;
            }

            ProductCurrencyPrice::query()
                ->where('product_id', $product->id)
                ->when(
                    $keep !== [],
                    fn ($q) => $q->whereNotIn('currency', $keep),
                    fn ($q) => $q,
                )
                ->delete();
        });
    }
}
