<?php

declare(strict_types=1);

namespace App\Agovena\Modules;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Customer\AccountNavItem;
use App\Agovena\Customer\AccountOverviewCard;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Customer\CustomerAccountOverview;
use App\Http\Middleware\SyncStaffPermissions;
use App\Models\Customer;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/**
 * Agovena-owned registration surface for Modules.
 * Intentionally free of Livewire/BEM — Modules register domain + Admin contracts only.
 */
final class ModuleContext
{
    public function __construct(
        private readonly AdminRegistrar $admin,
        private readonly ProductCapabilityRegistry $capabilities,
        private readonly CustomerAccountNav $customerAccountNav,
        private readonly CustomerAccountOverview $customerAccountOverview,
        private readonly Dispatcher $events,
        private readonly string $moduleId,
    ) {}

    public function moduleId(): string
    {
        return $this->moduleId;
    }

    public function admin(): AdminRegistrar
    {
        return $this->admin;
    }

    public function capabilities(): ProductCapabilityRegistry
    {
        return $this->capabilities;
    }

    public function customerAccountNav(AccountNavItem $item): void
    {
        $this->customerAccountNav->register($item);
    }

    /**
     * @param  callable(Customer): (AccountOverviewCard|null)  $factory
     */
    public function customerAccountOverview(string $id, callable $factory, int $sort = 0): void
    {
        $this->customerAccountOverview->register($id, $factory, $sort);
    }

    /**
     * Subscribe to a Core (or Module) event. Use for explicit policy such as refund consequences.
     * Core emits RefundRecorded / CreditNoteIssued / InvoiceVoided; Modules decide what to revoke or keep.
     *
     * @param  class-string  $event
     * @param  class-string|callable  $listener
     */
    public function listen(string $event, string|callable $listener): void
    {
        $this->events->listen($event, $listener);
    }

    /**
     * Register Admin (staff) routes under /admin. Pass a Livewire page class or controller action.
     *
     * @param  callable(): void  $routes
     */
    public function adminRoutes(callable $routes): void
    {
        Route::group([
            'middleware' => ['web', 'auth', SyncStaffPermissions::class, 'admin.access'],
            'prefix' => 'admin',
            'as' => 'admin.',
        ], $routes);

        app(Router::class)->getRoutes()->refreshNameLookups();
        app(Router::class)->getRoutes()->refreshActionLookups();
    }

    /**
     * Register verified customer account routes under /account.
     *
     * @param  callable(): void  $routes
     */
    public function customerRoutes(callable $routes): void
    {
        Route::group([
            'middleware' => ['web', 'auth', 'customer.verified'],
            'prefix' => 'account',
            'as' => 'customer.',
        ], $routes);

        app(Router::class)->getRoutes()->refreshNameLookups();
        app(Router::class)->getRoutes()->refreshActionLookups();
    }

    /**
     * Register authenticated customer API routes under /api/v1.
     *
     * @param  callable(): void  $routes
     */
    public function apiRoutes(callable $routes): void
    {
        Route::group([
            'middleware' => ['api', 'auth:sanctum', 'throttle:api'],
            'prefix' => 'api/v1',
            'as' => 'api.v1.',
        ], $routes);

        app(Router::class)->getRoutes()->refreshNameLookups();
        app(Router::class)->getRoutes()->refreshActionLookups();
    }
}
