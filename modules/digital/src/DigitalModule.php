<?php

declare(strict_types=1);

namespace Agovena\Modules\Digital;

use Agovena\Modules\Digital\Http\Controllers\DownloadController;
use Agovena\Modules\Digital\Http\Livewire\Admin\AssetsIndex;
use Agovena\Modules\Digital\Http\Livewire\Customer\DownloadsIndex;
use Agovena\Modules\Digital\Listeners\GrantDigitalEntitlementsWhenOrderPaid;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Customer\AccountNavItem;
use App\Agovena\Modules\Contracts\Module;
use App\Agovena\Modules\ModuleContext;
use App\Events\OrderPaid;
use Illuminate\Support\Facades\Route;

final class DigitalModule implements Module
{
    public function id(): string
    {
        return 'digital';
    }

    public function register(ModuleContext $context): void
    {
        $context->capabilities()->register(new ProductCapabilityDefinition(
            key: 'digital',
            label: 'admin.products.capabilities.digital',
            description: 'admin.products.capabilities.digital_help',
            providedByModule: $this->id(),
        ));

        $context->admin()->permission('digital.view', 'admin.permissions.digital.view');
        $context->admin()->permission('digital.manage', 'admin.permissions.digital.manage');

        $context->admin()->navigation(new NavigationItem(
            id: 'digital-assets',
            label: 'admin.nav.digital',
            group: 'admin.nav_groups.commerce',
            href: '/admin/digital/assets',
            icon: 'package',
            sort: 17,
            permission: 'digital.view',
        ));

        $context->customerAccountNav(new AccountNavItem(
            id: 'digital-downloads',
            label: 'digital::customer.nav',
            route: 'customer.downloads',
            section: 'downloads',
            sort: 35,
        ));

        $context->listen(OrderPaid::class, GrantDigitalEntitlementsWhenOrderPaid::class);

        $context->adminRoutes(function (): void {
            Route::get('/digital/assets', AssetsIndex::class)->name('digital.assets');
        });

        $context->customerRoutes(function (): void {
            Route::get('/downloads', DownloadsIndex::class)->name('downloads');
            Route::get('/downloads/{token}', DownloadController::class)->name('downloads.file');
        });
    }
}
