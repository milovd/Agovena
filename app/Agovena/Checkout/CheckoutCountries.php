<?php

declare(strict_types=1);

namespace App\Agovena\Checkout;

/**
 * ISO 3166-1 alpha-2 country options for checkout and account addresses.
 * Labels come from PHP intl (localized), not a hardcoded shortlist.
 */
final class CheckoutCountries
{
    /** @var list<string>|null */
    private static ?array $codes = null;

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        if (self::$codes !== null) {
            return self::$codes;
        }

        $codes = [];

        foreach (range('A', 'Z') as $first) {
            foreach (range('A', 'Z') as $second) {
                $code = $first.$second;
                $name = locale_get_display_region('-'.$code, 'en');
                if (! is_string($name) || $name === '' || $name === $code) {
                    continue;
                }
                $codes[] = $code;
            }
        }

        return self::$codes = $codes;
    }

    /**
     * @return array<string, string>
     */
    public static function options(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $options = [];

        foreach (self::codes() as $code) {
            $label = locale_get_display_region('-'.$code, $locale);
            $options[$code] = is_string($label) && $label !== '' && $label !== $code
                ? $label
                : $code;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }

    public static function isValid(string $code): bool
    {
        return in_array(strtoupper($code), self::codes(), true);
    }
}
