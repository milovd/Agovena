<?php

declare(strict_types=1);

namespace App\Agovena\Modules;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Customer\AccountNavItem;
use App\Agovena\Customer\CustomerAccountNav;
use App\Http\Middleware\SyncStaffPermissions;
use Illuminate\Contracts\Events\Dispatcher;
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
        Route::middleware(['web', 'auth:staff', SyncStaffPermissions::class])
            ->prefix('admin')
            ->name('admin.')
            ->group($routes);
    }

    /**
     * Register verified customer account routes under /account.
     *
     * @param  callable(): void  $routes
     */
    public function customerRoutes(callable $routes): void
    {
        Route::middleware(['web', 'auth:customer', 'customer.verified'])
            ->prefix('account')
            ->name('customer.')
            ->group($routes);
    }
}
