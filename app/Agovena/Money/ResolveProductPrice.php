<?php

declare(strict_types=1);

namespace App\Agovena\Money;

use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Models\Product;
use App\Models\ProductCurrencyPrice;
use Throwable;

/**
 * Resolves the sellable/display price for a product in a target currency.
 *
 * Priority: native product currency → manual override → FX conversion (when enabled) → unavailable.
 */
final class ResolveProductPrice
{
    public const SOURCE_NATIVE = 'native';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_CONVERTED = 'converted';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly StorefrontPreferences $preferences,
        private readonly CurrencyConverter $converter,
    ) {}

    public function autoConversionEnabled(): bool
    {
        try {
            $value = $this->settings->get('general', 'auto_currency_conversion', true);
        } catch (Throwable) {
            return true;
        }

        if (is_bool($value)) {
            return $value;
        }

        return $value === 1 || $value === '1' || $value === 'true';
    }

    public function resolve(Product $product, ?string $currency = null): ?ResolvedProductPrice
    {
        $target = strtoupper($currency ?? $this->preferences->currencyCode());
        $native = strtoupper($product->currency);

        if ($native === $target) {
            return new ResolvedProductPrice($product->money(), self::SOURCE_NATIVE);
        }

        $manual = $this->manualPrice($product, $target);
        if ($manual !== null) {
            return new ResolvedProductPrice(
                Money::of($manual->price_amount, $target),
                self::SOURCE_MANUAL,
            );
        }

        if (! $this->autoConversionEnabled()) {
            return null;
        }

        try {
            $converted = $this->converter->convertMoney($product->money(), $target);

            return new ResolvedProductPrice($converted, self::SOURCE_CONVERTED);
        } catch (Throwable) {
            return null;
        }
    }

    public function isAvailable(Product $product, ?string $currency = null): bool
    {
        return $this->resolve($product, $currency) !== null;
    }

    private function manualPrice(Product $product, string $currency): ?ProductCurrencyPrice
    {
        if ($product->relationLoaded('currencyPrices')) {
            $match = $product->currencyPrices->first(
                static fn (ProductCurrencyPrice $row): bool => strtoupper($row->currency) === $currency,
            );

            return $match instanceof ProductCurrencyPrice ? $match : null;
        }

        return ProductCurrencyPrice::query()
            ->where('product_id', $product->id)
            ->where('currency', $currency)
            ->first();
    }
}
