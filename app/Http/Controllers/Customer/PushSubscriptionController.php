<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Agovena\Notifications\VapidKeyStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PushSubscriptionController
{
    public function config(VapidKeyStore $vapid): JsonResponse
    {
        $config = $vapid->get();

        return response()->json([
            'configured' => $config !== null,
            'publicKey' => $config['publicKey'] ?? null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
        ]);
        $user = current_user();
        abort_unless($user !== null, 403);

        $user->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $validated['endpoint']],
            [
                'p256dh_key' => $validated['keys']['p256dh'],
                'auth_key' => $validated['keys']['auth'],
                'user_agent' => Str::limit($request->userAgent() ?? '', 512, ''),
            ],
        );

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2048'],
        ]);
        $user = current_user();
        abort_unless($user !== null, 403);

        $user->pushSubscriptions()->where('endpoint', $validated['endpoint'])->delete();

        return response()->json(['subscribed' => false]);
    }
}
