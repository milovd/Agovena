<?php

declare(strict_types=1);

namespace Agovena\Modules\Subscriptions;

use Agovena\Modules\Subscriptions\Http\Livewire\Admin\SubscriptionShow;
use Agovena\Modules\Subscriptions\Http\Livewire\Admin\SubscriptionsIndex;
use Agovena\Modules\Subscriptions\Http\Livewire\Customer\SubscriptionsIndex as CustomerSubscriptionsIndex;
use Agovena\Modules\Subscriptions\Listeners\CreateSubscriptionsWhenOrderPaid;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Customer\AccountNavItem;
use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;
use App\Events\OrderPaid;
use Illuminate\Support\Facades\Route;

final class SubscriptionsModule implements Module
{
    public function id(): string
    {
        return 'subscriptions';
    }

    public function register(ModuleContext $context): void
    {
        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'subscribable',
            label: 'admin.products.capabilities.subscribable',
            description: 'admin.products.capabilities.subscribable_help',
            providedByModule: $this->id(),
        ));

        $context->admin()->permission('subscriptions.view', 'admin.permissions.subscriptions.view');
        $context->admin()->permission('subscriptions.manage', 'admin.permissions.subscriptions.manage');

        $context->admin()->navigation(new NavigationItem(
            id: 'subscriptions',
            label: 'admin.nav.subscriptions',
            group: 'admin.nav_groups.commerce',
            href: '/admin/subscriptions',
            icon: 'package',
            sort: 16,
            permission: 'subscriptions.view',
        ));

        $context->customerAccountNav(new AccountNavItem(
            id: 'subscriptions',
            label: 'subscriptions::customer.nav',
            route: 'customer.subscriptions',
            section: 'subscriptions',
            sort: 30,
        ));

        $context->listen(OrderPaid::class, CreateSubscriptionsWhenOrderPaid::class);

        $context->adminRoutes(function (): void {
            Route::get('/subscriptions', SubscriptionsIndex::class)->name('subscriptions.index');
            Route::get('/subscriptions/{subscription}', SubscriptionShow::class)->name('subscriptions.show');
        });

        $context->customerRoutes(function (): void {
            Route::get('/subscriptions', CustomerSubscriptionsIndex::class)->name('subscriptions');
        });
    }
}
