<?php

declare(strict_types=1);

namespace App\Agovena\Storefront;

use App\Agovena\Settings\SettingsRepository;
use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Storefront visitor preferences (locale + currency) backed by the session.
 */
final class StorefrontPreferences
{
    public const SESSION_LOCALE = 'storefront.locale';

    public const SESSION_CURRENCY = 'storefront.currency';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function locale(): string
    {
        $session = Session::get(self::SESSION_LOCALE);
        if (is_string($session) && $this->isAvailableLocale($session)) {
            return $session;
        }

        return $this->siteLocale();
    }

    public function setLocale(string $locale): void
    {
        if (! $this->isAvailableLocale($locale)) {
            return;
        }

        Session::put(self::SESSION_LOCALE, $locale);
    }

    public function currencyCode(): string
    {
        $session = Session::get(self::SESSION_CURRENCY);
        if (is_string($session) && $this->isActiveCurrency($session)) {
            return strtoupper($session);
        }

        return $this->baseCurrency();
    }

    public function setCurrency(string $code): void
    {
        $code = strtoupper(trim($code));
        if (! $this->isActiveCurrency($code)) {
            return;
        }

        Session::put(self::SESSION_CURRENCY, $code);
    }

    /**
     * @return array<string, string>
     */
    public function availableLocales(): array
    {
        /** @var array<string, string> $locales */
        $locales = config('agovena.locales', ['en' => 'English']);

        return $locales;
    }

    /**
     * @return Collection<int, Currency>
     */
    public function availableCurrencies(): Collection
    {
        try {
            return Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    public function catalogCurrencyFilter(): null
    {
        // Display conversion handles preferred currency; do not hide products priced in other currencies.
        return null;
    }

    public function siteLocale(): string
    {
        $locale = (string) config('app.locale', 'en');

        try {
            $configured = $this->settings->get('general', 'locale', $locale);
            if (is_string($configured) && $configured !== '') {
                $locale = $configured;
            }
        } catch (\Throwable) {
            // Settings may be unavailable during install.
        }

        if (! $this->isAvailableLocale($locale)) {
            return (string) config('app.fallback_locale', 'en');
        }

        return $locale;
    }

    public function baseCurrency(): string
    {
        try {
            $base = (string) $this->settings->get('general', 'base_currency', 'EUR');
            if ($base !== '' && $this->isActiveCurrency($base)) {
                return strtoupper($base);
            }
        } catch (\Throwable) {
            // Fall through.
        }

        $first = $this->availableCurrencies()->first();

        return $first instanceof Currency ? strtoupper($first->code) : 'EUR';
    }

    public function isAvailableLocale(string $locale): bool
    {
        return array_key_exists($locale, $this->availableLocales());
    }

    public function isActiveCurrency(string $code): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return false;
        }

        try {
            return Currency::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
