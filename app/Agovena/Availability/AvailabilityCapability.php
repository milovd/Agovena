<?php

declare(strict_types=1);

namespace App\Agovena\Availability;

use App\Agovena\Admin\NavigationItem;
use App\Agovena\Availability\Http\Livewire\Admin\StocksIndex;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Modules\ModuleContext;
use Illuminate\Support\Facades\Route;

final class AvailabilityCapability
{
    public function id(): string
    {
        return 'availability';
    }

    public function register(ModuleContext $context): void
    {
        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'availability',
            label: 'admin.products.capabilities.inventory',
            description: 'admin.products.capabilities.inventory_help',
        ));

        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'physical',
            label: 'admin.products.capabilities.physical',
            description: 'admin.products.capabilities.physical_help',

        ));

        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'inventory',
            label: 'admin.products.capabilities.inventory',
            description: 'admin.products.capabilities.inventory_help',
            requires: ['physical'],

        ));

        $context->admin()->permission('inventory.view', 'admin.permissions.inventory.view');
        $context->admin()->permission('inventory.manage', 'admin.permissions.inventory.manage');

        $context->admin()->navigation(new NavigationItem(
            id: 'inventory-stocks',
            label: 'admin.nav.inventory',
            group: 'admin.nav_groups.fulfillment',
            href: '/admin/inventory',
            icon: 'warehouse',
            sort: 18,
            permission: 'inventory.view',
        ));

        $context->adminRoutes(function (): void {
            Route::get('/inventory', StocksIndex::class)->name('inventory.index');
        });
    }
}
