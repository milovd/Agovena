<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

final class CheckoutCountries
{
    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return ['NL', 'BE', 'DE', 'FR', 'GB', 'US'];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::codes() as $code) {
            $options[$code] = __('storefront.countries.'.$code);
        }

        return $options;
    }
}
