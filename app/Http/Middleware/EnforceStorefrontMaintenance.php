<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Maintenance\MaintenanceMode;
use App\Agovena\Maintenance\StorefrontMaintenanceException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Agovena storefront maintenance. Unlike `artisan down` this only closes the
 * storefront, customer account and public API: the Admin, staff sign-in,
 * payment webhooks and callbacks keep working. Staff with Admin access browse
 * the storefront normally. Livewire replays this middleware (persistent
 * middleware) against the page a component was rendered on.
 */
final class EnforceStorefrontMaintenance
{
    /**
     * Paths that are never closed, whatever their route name.
     *
     * @var list<string>
     */
    private const ALWAYS_OPEN_PATHS = [
        'admin',
        'admin/*',
        'webhooks/*',
        '*/webhooks/*',
        '*/webhook/*',
        '*/callback/*',
        'up',
        'install',
        'install/*',
    ];

    /**
     * Staff sign-in, payment returns and technical endpoints.
     *
     * @var list<string>
     */
    private const ALWAYS_OPEN_ROUTES = [
        'admin.*',
        'login',
        'admin.login',
        'customer.login',
        'two-factor.challenge',
        'password.request',
        'password.reset',
        'customer.password.request',
        'customer.password.reset',
        'customer.logout',
        'oauth.*',
        'install',
        'installer.*',
        // Payment returns reconcile the provider status for orders already paid.
        'storefront.payment.status',
        'storefront.order.confirmation',
        'storefront.preferences.locale',
        'privacy.consent',
        'seo.robots',
        'notifications.service-worker',
        // The Livewire endpoint itself; storefront components are checked through persistent middleware.
        '*livewire.*',
        // Provider webhooks and callbacks, including routes registered by packages.
        '*webhook*',
        '*callback*',
    ];

    public function __construct(private readonly MaintenanceMode $maintenance) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->allowsDuringMaintenance($request)) {
            return $next($request);
        }

        $state = $this->maintenance->state();
        if (! $state->enabled) {
            return $next($request);
        }

        if (! $this->isApi($request) && $this->isStaff($request)) {
            return $next($request);
        }

        throw new StorefrontMaintenanceException($state, $this->maintenance->retryAfterSeconds($state));
    }

    /**
     * Whether the request stays open while maintenance is on, for everyone.
     */
    public function allowsDuringMaintenance(Request $request): bool
    {
        if ($request->is(...self::ALWAYS_OPEN_PATHS) || $request->is(...$this->configuredPaths())) {
            return true;
        }

        $name = $request->route()?->getName();

        return is_string($name) && $name !== '' && Str::is(self::ALWAYS_OPEN_ROUTES, $name);
    }

    private function isApi(Request $request): bool
    {
        return $request->is('api', 'api/*');
    }

    private function isStaff(Request $request): bool
    {
        try {
            $user = $request->user();

            return $user instanceof User && $user->canAccessAdmin();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function configuredPaths(): array
    {
        $paths = config('agovena.maintenance.except', []);

        return is_array($paths)
            ? array_values(array_filter($paths, static fn (mixed $path): bool => is_string($path) && $path !== ''))
            : [];
    }
}
