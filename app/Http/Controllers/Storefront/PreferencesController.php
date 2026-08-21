<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Agovena\Cart\CartService;
use App\Agovena\Storefront\StorefrontPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PreferencesController
{
    public function locale(Request $request, StorefrontPreferences $preferences): RedirectResponse
    {
        $locale = (string) $request->validate([
            'locale' => ['required', 'string', 'max:12'],
        ])['locale'];

        if (! $preferences->isAvailableLocale($locale)) {
            abort(422);
        }

        $preferences->setLocale($locale);

        return back();
    }

    public function currency(
        Request $request,
        StorefrontPreferences $preferences,
        CartService $cart,
    ): RedirectResponse {
        $code = strtoupper((string) $request->validate([
            'currency' => ['required', 'string', 'size:3'],
        ])['currency']);

        if (! $preferences->isActiveCurrency($code)) {
            abort(422);
        }

        $previous = $preferences->currencyCode();
        $preferences->setCurrency($code);

        if ($previous !== $code && $cart->lines() !== []) {
            $subtotal = $cart->subtotal();
            if ($subtotal === null || strtoupper($subtotal->currency) !== $code) {
                $cart->clear();
                session()->flash('status', __('storefront.preferences.currency_cart_cleared', ['currency' => $code]));
            }
        }

        return back();
    }
}
