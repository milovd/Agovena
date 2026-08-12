<?php

declare(strict_types=1);

namespace Agovena\Modules\Provisioning;

use Agovena\Modules\Provisioning\Http\Livewire\Admin\InstanceShow;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\InstancesIndex;
use Agovena\Modules\Provisioning\Http\Livewire\Customer\ServicesIndex;
use Agovena\Modules\Provisioning\Listeners\CreateServiceInstancesWhenOrderPaid;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Customer\AccountNavItem;
use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;
use App\Events\OrderPaid;
use Illuminate\Support\Facades\Route;

final class ProvisioningModule implements Module
{
    public function id(): string
    {
        return 'provisioning';
    }

    public function register(ModuleContext $context): void
    {
        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'provisionable',
            label: 'admin.products.capabilities.provisionable',
            description: 'admin.products.capabilities.provisionable_help',
            providedByModule: $this->id(),
        ));

        $context->admin()->permission('provisioning.view', 'admin.permissions.provisioning.view');
        $context->admin()->permission('provisioning.manage', 'admin.permissions.provisioning.manage');

        $context->admin()->navigation(new NavigationItem(
            id: 'provisioning',
            label: 'admin.nav.provisioning',
            group: 'admin.nav_groups.commerce',
            href: '/admin/provisioning',
            icon: 'package',
            sort: 15,
            permission: 'provisioning.view',
        ));

        $context->customerAccountNav(new AccountNavItem(
            id: 'services',
            label: 'provisioning::customer.nav',
            route: 'customer.services',
            section: 'services',
            sort: 25,
        ));

        $context->listen(OrderPaid::class, CreateServiceInstancesWhenOrderPaid::class);

        $context->adminRoutes(function (): void {
            Route::get('/provisioning', InstancesIndex::class)->name('provisioning.index');
            Route::get('/provisioning/{instance}', InstanceShow::class)->name('provisioning.show');
        });

        $context->customerRoutes(function (): void {
            Route::get('/services', ServicesIndex::class)->name('services');
        });
    }
}
