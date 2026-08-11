<?php

declare(strict_types=1);

namespace App\Providers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\DashboardWidget;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Admin\NavigationItem;
use App\Agovena\Admin\SettingsField;
use App\Agovena\Admin\SettingsGroup;
use App\Agovena\Cart\CartRepository;
use App\Agovena\Cart\SessionCartRepository;
use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AgovenaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminRegistrar::class, InMemoryAdminRegistrar::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(CurrencyCatalog::class);
        $this->app->bind(CartRepository::class, SessionCartRepository::class);

        $this->mergeConfigFrom(__DIR__.'/../../config/agovena.php', 'agovena');
    }

    public function boot(): void
    {
        /** @var AdminRegistrar $admin */
        $admin = $this->app->make(AdminRegistrar::class);

        $this->registerNavigation($admin);
        $this->registerPermissions($admin);
        $this->registerSettings($admin);
        $this->registerWidgets($admin);

        $theme = $this->app->make(ThemeManager::class)->active();
        View::addNamespace('theme', $theme->viewsPath);

        View::composer(['layouts.admin', 'layouts.admin-guest', 'theme::layouts.storefront'], function ($view): void {
            /** @var SettingsRepository $settings */
            $settings = $this->app->make(SettingsRepository::class);
            $siteName = (string) $settings->get('general', 'site_name', config('app.name', 'Agovena'));
            $logoPath = $settings->get('branding', 'logo_path');
            $faviconPath = $settings->get('branding', 'favicon_path');
            $view->with('siteName', $siteName);
            $view->with('brandingLogoPath', is_string($logoPath) && $logoPath !== '' ? $logoPath : null);
            $favicon = is_string($faviconPath) && $faviconPath !== '' ? $faviconPath : null;
            if ($favicon === null && is_string($logoPath) && $logoPath !== '') {
                $favicon = $logoPath;
            }
            $view->with('brandingFaviconPath', $favicon);
        });

        View::composer('layouts.admin', function ($view): void {
            if (array_key_exists('navigation', $view->getData())) {
                return;
            }

            /** @var AdminRegistrar $admin */
            $admin = $this->app->make(AdminRegistrar::class);
            $view->with('navigation', $admin->navigationItems());
        });
    }

    private function registerNavigation(AdminRegistrar $admin): void
    {
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
            id: 'categories',
            label: 'Categories',
            group: 'Commerce',
            href: '/admin/categories',
            sort: 15,
            permission: 'categories.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'orders',
            label: 'Orders',
            group: 'Commerce',
            href: '/admin/orders',
            sort: 20,
            permission: 'orders.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'settings-general',
            label: 'General',
            group: 'System',
            href: '/admin/settings/general',
            sort: 100,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'settings-branding',
            label: 'Branding',
            group: 'System',
            href: '/admin/settings/branding',
            sort: 110,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'settings-store',
            label: 'Store',
            group: 'System',
            href: '/admin/settings/store',
            sort: 120,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'currencies',
            label: 'Currencies',
            group: 'System',
            href: '/admin/currencies',
            sort: 130,
            permission: 'currencies.view',
        ));
    }

    private function registerPermissions(AdminRegistrar $admin): void
    {
        $admin->permission('dashboard.view', 'View dashboard');
        $admin->permission('products.view', 'View products');
        $admin->permission('products.create', 'Create products');
        $admin->permission('products.update', 'Update products');
        $admin->permission('categories.view', 'View categories');
        $admin->permission('categories.create', 'Create categories');
        $admin->permission('categories.update', 'Update categories');
        $admin->permission('orders.view', 'View orders');
        $admin->permission('payments.record', 'Record payments');
        $admin->permission('settings.view', 'View settings');
        $admin->permission('settings.update', 'Update settings');
        $admin->permission('currencies.view', 'View currencies');
        $admin->permission('currencies.create', 'Create currencies');
        $admin->permission('currencies.update', 'Update currencies');
    }

    private function registerSettings(AdminRegistrar $admin): void
    {
        $admin->settingsGroup(new SettingsGroup(
            id: 'general',
            label: 'General',
            permission: 'settings.view',
            sort: 10,
            description: 'Site identity and regional defaults.',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'branding',
            label: 'Branding',
            permission: 'settings.view',
            sort: 20,
            description: 'Logo and favicon used across Admin and storefront.',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'store',
            label: 'Store',
            permission: 'settings.view',
            sort: 30,
            description: 'Commerce defaults that do not belong in environment secrets.',
        ));

        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'site_name',
            label: 'Site name',
            type: 'string',
            default: config('app.name', 'Agovena'),
            help: 'Shown in Admin and the default storefront header.',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'locale',
            label: 'Locale',
            type: 'string',
            default: config('app.locale', 'en'),
            sort: 20,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'timezone',
            label: 'Timezone',
            type: 'timezone',
            default: config('app.timezone', 'UTC'),
            sort: 30,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'base_currency',
            label: 'Base currency',
            type: 'currency',
            default: 'EUR',
            help: 'Default catalog currency. Create currencies with prefix/suffix under System → Currencies.',
            sort: 40,
        ));

        $admin->settingsField(new SettingsField(
            group: 'branding',
            key: 'logo_path',
            label: 'Logo',
            type: 'image',
            default: null,
            help: 'PNG, JPG, WebP or SVG. Max 2 MB. You can also use it as the favicon.',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'branding',
            key: 'favicon_path',
            label: 'Favicon',
            type: 'image',
            default: null,
            help: 'Optional. Leave empty and enable “use logo as favicon”, or upload a separate icon.',
            sort: 20,
        ));

        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'customer_registration',
            label: 'Customer registration',
            type: 'select',
            default: 'disabled',
            options: ['disabled', 'optional', 'required'],
            help: 'Customer accounts are not required for guest checkout yet.',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'order_number_prefix',
            label: 'Order number prefix',
            type: 'string',
            default: 'AGO',
            sort: 20,
        ));
    }

    private function registerWidgets(AdminRegistrar $admin): void
    {
        $admin->widget(new DashboardWidget(
            id: 'commerce-stats',
            label: 'Commerce overview',
            view: 'admin.widgets.commerce-stats',
            permission: 'dashboard.view',
            sort: 10,
        ));
        $admin->widget(new DashboardWidget(
            id: 'recent-orders',
            label: 'Recent orders',
            view: 'admin.widgets.recent-orders',
            permission: 'orders.view',
            sort: 20,
        ));
        $admin->widget(new DashboardWidget(
            id: 'attention',
            label: 'Needs attention',
            view: 'admin.widgets.attention',
            permission: 'dashboard.view',
            sort: 30,
        ));
    }
}
