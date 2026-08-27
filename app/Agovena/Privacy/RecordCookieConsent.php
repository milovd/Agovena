<?php

declare(strict_types=1);

namespace App\Agovena\Privacy;

use App\Models\ConsentEvent;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

final class RecordCookieConsent
{
    public const COOKIE_NAME = 'agovena_consent';

    private const CONSENT_VERSION = '1';

    /** @return array{version: string, choice: string, categories: array<string, bool>, id: string, date: string} */
    public function record(Request $request, string $choice, string $source = 'banner'): array
    {
        $categories = $choice === 'analytics'
            ? [
                'necessary' => true,
                'functional' => false,
                'analytics' => true,
                'marketing' => false,
            ]
            : [
                'necessary' => true,
                'functional' => false,
                'analytics' => false,
                'marketing' => false,
            ];

        $event = ConsentEvent::query()->create([
            'user_id' => $request->user()?->getAuthIdentifier(),
            'consent_version' => self::CONSENT_VERSION,
            'choice' => $choice,
            'source' => $source,
            'ip_hash' => $this->hash($request->ip()),
            'user_agent_hash' => $this->hash($request->userAgent()),
        ]);

        $event->categories()->createMany(array_map(
            static fn (bool $decision, string $category): array => [
                'category' => $category,
                'decision' => $decision,
            ],
            $categories,
            array_keys($categories),
        ));

        return [
            'version' => self::CONSENT_VERSION,
            'choice' => $choice,
            'categories' => $categories,
            'id' => (string) $event->getKey(),
            'date' => $event->created_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /** @param array{version: string, choice: string, categories: array<string, bool>, id: string, date: string} $payload */
    public function cookie(array $payload): Cookie
    {
        return app(CookieJar::class)->make(
            self::COOKIE_NAME,
            json_encode($payload, JSON_THROW_ON_ERROR),
            60 * 24 * 180,
            '/',
            null,
            (bool) config('session.secure'),
            true,
            false,
            'lax',
        );
    }

    private function hash(?string $value): string
    {
        return hash_hmac('sha256', (string) $value, (string) config('app.key'));
    }
}
