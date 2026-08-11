<?php

declare(strict_types=1);

namespace App\Providers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Cart\CartRepository;
use App\Agovena\Cart\SessionCartRepository;
use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AgovenaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminRegistrar::class, InMemoryAdminRegistrar::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->bind(CartRepository::class, SessionCartRepository::class);
    }

    public function boot(): void
    {
        /** @var InMemoryAdminRegistrar $admin */
        $admin = $this->app->make(AdminRegistrar::class);

        $admin->navigation(new NavigationItem(
            id: 'dashboard',
            label: 'Dashboard',
            group: 'Overview',
            href: '/admin',
            sort: 0,
            permission: 'dashboard.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'products',
            label: 'Products',
            group: 'Commerce',
            href: '/admin/products',
            sort: 10,
            permission: 'products.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'orders',
            label: 'Orders',
            group: 'Commerce',
            href: '/admin/orders',
            sort: 20,
            permission: 'orders.view',
        ));

        $admin->permission('dashboard.view', 'View dashboard');
        $admin->permission('products.view', 'View products');
        $admin->permission('products.create', 'Create products');
        $admin->permission('products.update', 'Update products');
        $admin->permission('orders.view', 'View orders');
        $admin->permission('payments.record', 'Record payments');

        $theme = $this->app->make(ThemeManager::class)->active();
        View::addNamespace('theme', $theme->viewsPath);
    }
}
