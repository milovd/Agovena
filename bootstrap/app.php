<?php

use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Http\Middleware\EnsureAgovenaInstalled;
use App\Http\Middleware\EnsureApplicationSchemaIsCurrent;
use App\Http\Middleware\EnsureCanAccessAdmin;
use App\Http\Middleware\EnsureCustomerEmailIsVerified;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('agovena:process-subscription-renewals')
            ->everyMinute()
            ->withoutOverlapping(10);
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [
            EnsureAgovenaInstalled::class,
            EnsureApplicationSchemaIsCurrent::class,
        ]);
        $middleware->web(append: [
            SetLocale::class,
        ]);
        $middleware->alias([
            'customer.verified' => EnsureCustomerEmailIsVerified::class,
            'admin.access' => EnsureCanAccessAdmin::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
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

        $exceptions->render(function (QueryException $e, Request $request) {
            try {
                $schema = app(ApplicationSchemaStatus::class);
                if (! $schema->isMissingRelationException($e) || $schema->isCurrent()) {
                    return null;
                }
            } catch (Throwable) {
                return null;
            }

            if ($request->expectsJson() || $request->is('livewire/*') || $request->is('api/*')) {
                return response()->json([
                    'message' => __('schema.update_required.title'),
                ], 503);
            }

            return response()->view('schema.update-required', $schema->viewData(), 503);
        });
    })->create();
