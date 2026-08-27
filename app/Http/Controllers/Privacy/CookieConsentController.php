<?php

declare(strict_types=1);

namespace App\Http\Controllers\Privacy;

use App\Agovena\Privacy\RecordCookieConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CookieConsentController
{
    public function __invoke(Request $request, RecordCookieConsent $record): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'choice' => ['required', 'string', Rule::in(['analytics', 'necessary'])],
        ]);

        $payload = $record->record($request, $validated['choice']);

        if ($request->expectsJson()) {
            return response()->json(['consent' => $payload])->withCookie($record->cookie($payload));
        }

        return redirect()->route('storefront.home')->withCookie($record->cookie($payload));
    }
}
