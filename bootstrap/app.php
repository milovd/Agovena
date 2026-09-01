<?php

use App\Agovena\Api\ApiError;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Http\Middleware\EnforceAbusePolicy;
use App\Http\Middleware\EnforceApiIpAllowlist;
use App\Http\Middleware\EnsureAgovenaInstalled;
use App\Http\Middleware\EnsureApiTokenAbility;
use App\Http\Middleware\EnsureCanAccessAdmin;
use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use App\Http\Middleware\EnsurePrivilegedTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->call(static function (): void {
            Cache::put('agovena:scheduler:heartbeat', now()->toIso8601String(), now()->addHours(2));
        })->everyMinute()->name('agovena-scheduler-heartbeat');
        $schedule->command('agovena:deliver-webhooks')
            ->everyMinute()
            ->withoutOverlapping(10);
        $schedule->command('agovena:reconcile-payment-webhooks')
            ->everyMinute()
            ->withoutOverlapping(10);
        $schedule->command('agovena:recover-queue-outbox')
            ->everyMinute()
            ->withoutOverlapping(10);
        $schedule->command('agovena:process-subscription-renewals')
            ->everyMinute()
            ->withoutOverlapping(10);
        $schedule->command('agovena:sync-provisioning')
            ->everyMinute()
            ->withoutOverlapping(10);
        $schedule->command('agovena:cancel-stale-unpaid-orders')
            ->hourly()
            ->withoutOverlapping(30);
        $schedule->command('agovena:prune-logs')
            ->daily()
            ->withoutOverlapping(120);
        $schedule->command('agovena:backup')
            ->dailyAt('02:30')
            ->withoutOverlapping(120);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $proxies = env('TRUSTED_PROXIES');
        if (is_string($proxies) && $proxies !== '') {
            $proxyAddresses = array_values(array_filter(array_map('trim', explode(',', $proxies))));
            // Wildcard proxy trust would let clients spoof the IP allowlist.
            if ($proxyAddresses !== [] && ! in_array('*', $proxyAddresses, true)) {
                $middleware->trustProxies(at: $proxyAddresses);
            }
        }
        $middleware->web(prepend: [
            EnsureAgovenaInstalled::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
            SecurityHeaders::class,
        ]);
        $middleware->api(prepend: [
            EnsureAgovenaInstalled::class,
            SetLocale::class,
            EnforceApiIpAllowlist::class,
        ]);
        $middleware->api(append: [
            SecurityHeaders::class,
        ]);
        $middleware->alias([
            'api.ability' => EnsureApiTokenAbility::class,
            'customer.verified' => EnsureCustomerEmailIsVerified::class,
            'admin.access' => EnsureCanAccessAdmin::class,
            'admin.2fa' => EnsurePrivilegedTwoFactor::class,
            'abuse' => EnforceAbusePolicy::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api') || $request->is('api/*')) {
                return null;
            }

            return route('login');
        });
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();
            if ($user instanceof User && $user->canAccessAdmin() && ($request->is('admin') || $request->is('admin/*'))) {
                return route('admin.dashboard');
            }

            return route('customer.account');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::json('validation_error', $e->getMessage(), 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::json('unauthenticated', __('api.unauthenticated'), 401);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::json('rate_limited', __('api.rate_limited'), 429);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $code = match ($e->getStatusCode()) {
                401 => 'unauthenticated',
                403 => 'unauthorized',
                404 => 'not_found',
                409 => 'invalid_state',
                422 => 'invalid_state',
                429 => 'rate_limited',
                default => 'http_error',
            };

            $message = $e->getMessage() !== '' ? $e->getMessage() : __('api.http.'.$e->getStatusCode());

            return ApiError::json($code, $message, $e->getStatusCode());
        });

        $exceptions->render(function (QueryException $e, Request $request) {
            try {
                $schema = app(ApplicationSchemaStatus::class);
                if (! $schema->isMissingRelationException($e) || $schema->isCurrent()) {
                    return null;
                }
            } catch (Throwable) {
                return null;
            }

            $isAdmin = $request->is('admin') || $request->is('admin/*');
            if (! $isAdmin) {
                return null;
            }

            if ($request->expectsJson() || $request->is('livewire/*') || $request->is('api/*')) {
                return response()->json([
                    'message' => __('admin.updates.pending_title'),
                    'redirect' => route('admin.updates'),
                ], 503);
            }

            if ($request->routeIs('admin.updates')) {
                return response()->view('errors.503', status: 503);
            }

            return new RedirectResponse(route('admin.updates'));
        });
    })->create();
