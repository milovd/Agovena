<?php

declare(strict_types=1);

namespace App\Http\Controllers\Storefront;

use App\Agovena\Referrals\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

final class ReferralVisitController
{
    public function __invoke(string $code, ReferralService $referrals): RedirectResponse
    {
        if (! $referrals->isEnabled()) {
            return redirect()->route('storefront.home');
        }

        $visitorToken = request()->cookie(ReferralService::VISITOR_COOKIE);
        if (! is_string($visitorToken) || strlen($visitorToken) < 32) {
            $visitorToken = Str::random(64);
        }

        $visit = $referrals->recordVisit($code, hash('sha256', $visitorToken));
        if ($visit === null) {
            return redirect()->route('storefront.home');
        }

        $trackingCookie = cookie(
            ReferralService::TRACKING_COOKIE,
            $referrals->cookieValue($visit),
            $referrals->windowDaysFor($visit->code) * 1440,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax',
        );
        $visitorCookie = cookie(
            ReferralService::VISITOR_COOKIE,
            $visitorToken,
            60 * 24 * 365,
            '/',
            null,
            request()->isSecure(),
            true,
            false,
            'lax',
        );

        return redirect()->route('storefront.home')
            ->withCookie($trackingCookie)
            ->withCookie($visitorCookie);
    }
}
