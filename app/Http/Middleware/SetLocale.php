<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Storefront\StorefrontPreferences;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function __construct(private readonly StorefrontPreferences $preferences) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*')) {
            $locale = $this->preferences->siteLocale();
        } elseif ($request->is('install', 'install/*')) {
            $installerLocale = $request->cookie('installer_locale');
            $locale = is_string($installerLocale) && $this->preferences->isAvailableLocale($installerLocale)
                ? $installerLocale
                : $this->preferences->locale();
        } else {
            $locale = $this->preferences->locale();
        }

        App::setLocale($locale);

        return $next($request);
    }
}
