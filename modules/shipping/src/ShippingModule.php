<?php

declare(strict_types=1);

namespace Agovena\Modules\Shipping;

use Agovena\Modules\Shipping\Http\Livewire\Admin\MethodsIndex;
use Agovena\Modules\Shipping\Http\Livewire\Admin\OrderFulfillment;
use Agovena\Modules\Shipping\Http\Livewire\Admin\ZonesIndex;
use Agovena\Modules\Shipping\Listeners\CreateShipmentWhenOrderCreated;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Admin\OrderDetailSection;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;
use App\Events\OrderCreated;
use Illuminate\Support\Facades\Route;

final class ShippingModule implements Module
{
    public function id(): string
    {
        return 'shipping';
    }

    public function register(ModuleContext $context): void
    {
        // Also register physical so Shipping works without Inventory enabled.
        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'physical',
            label: 'admin.products.capabilities.physical',
            description: 'admin.products.capabilities.physical_help',
            providedByModule: $this->id(),
        ));

        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'shippable',
            label: 'admin.products.capabilities.shippable',
            description: 'admin.products.capabilities.shippable_help',
            requires: ['physical'],
            providedByModule: $this->id(),
        ));

        $context->admin()->permission('shipping.view', 'admin.permissions.shipping.view');
        $context->admin()->permission('shipping.manage', 'admin.permissions.shipping.manage');

        $context->admin()->navigation(new NavigationItem(
            id: 'shipping-methods',
            label: 'admin.nav.shipping',
            group: 'admin.nav_groups.services',
            href: '/admin/shipping/methods',
            icon: 'package',
            sort: 19,
            permission: 'shipping.view',
        ));

        $context->admin()->orderDetailSection(new OrderDetailSection(
            id: 'shipping-fulfillment',
            component: OrderFulfillment::class,
            sort: 40,
        ));

        $context->listen(OrderCreated::class, CreateShipmentWhenOrderCreated::class);

        $context->adminRoutes(function (): void {
            Route::get('/shipping/methods', MethodsIndex::class)->name('shipping.methods');
            Route::get('/shipping/zones', ZonesIndex::class)->name('shipping.zones');
        });
    }
}
