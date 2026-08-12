<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) config('app.locale', 'en');

        try {
            $configured = $this->settings->get('general', 'locale', $locale);
            if (is_string($configured) && $configured !== '') {
                $locale = $configured;
            }
        } catch (\Throwable) {
            // Settings table may not exist during install or early boot.
        }

        /** @var array<string, string> $available */
        $available = config('agovena.locales', ['en' => 'English']);
        if (! array_key_exists($locale, $available)) {
            $locale = (string) config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
