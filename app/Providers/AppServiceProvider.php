<?php

namespace App\Providers;

use App\Http\Middleware\EnforceAbusePolicy;
use App\Http\Middleware\EnsureCanAccessAdmin;
use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use App\Http\Middleware\EnsurePrivilegedTwoFactor;
use App\Models\PersonalAccessToken;
use Composer\CaBundle\CaBundle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
    }

    public function boot(): void
    {
        config(['livewire.csp_safe' => true]);

        // Livewire update requests only re-run route middleware listed here. Without
        // these, a suspended account, revoked Admin access, a lapsed privileged 2FA
        // session or an unverified customer email would not stop components that are
        // already open in a browser tab.
        Livewire::addPersistentMiddleware([
            EnforceAbusePolicy::class,
            EnsureCanAccessAdmin::class,
            EnsurePrivilegedTwoFactor::class,
            EnsureCustomerEmailIsVerified::class,
        ]);

        // WinGet/XAMPP PHP often ships without curl.cainfo; Composer's Mozilla
        // CA bundle keeps outbound HTTPS (Frankfurter, vatnode, etc.) verifiable.
        if (! $this->app->runningUnitTests()) {
            Http::globalOptions([
                'verify' => CaBundle::getSystemCaRootBundlePath(),
            ]);
        }

        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-catalog', function (Request $request) {
            return Limit::perMinute(120)->by((string) $request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            $email = strtolower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($request->ip().'|'.$email);
        });

        RateLimiter::for('api-sensitive', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(10)->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('oauth', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });
    }
}
