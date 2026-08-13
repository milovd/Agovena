<?php

declare(strict_types=1);

namespace App\Agovena\Support;

final class CountryList
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'FR' => 'France',
            'GB' => 'United Kingdom',
            'IE' => 'Ireland',
            'IT' => 'Italy',
            'LU' => 'Luxembourg',
            'NL' => 'Netherlands',
            'PL' => 'Poland',
            'SE' => 'Sweden',
            'US' => 'United States',
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::options());
    }
}
