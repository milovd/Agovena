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
use App\Agovena\Cart\CartService;
use App\Agovena\Cart\SessionCartRepository;
use App\Agovena\Catalog\ListStorefrontCategories;
use App\Agovena\Content\MenuResolver;
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

        View::composer('theme::layouts.storefront', function ($view): void {
            $cartCount = 0;
            try {
                $cartCount = $this->app->make(CartService::class)->itemCount();
            } catch (\Throwable) {
                $cartCount = 0;
            }

            $view->with('cartCount', $cartCount);

            /** @var MenuResolver $menus */
            $menus = $this->app->make(MenuResolver::class);
            $main = $menus->handle('header');
            $footer = $menus->handle('footer');
            $legal = $menus->handle('footer_legal');

            $view->with('themeMainNav', $main !== [] ? $this->flattenMenuLinks($main) : [
                ['label' => 'Deals', 'url' => route('storefront.home').'#catalog'],
                ['label' => 'About', 'url' => url('/about')],
            ]);
            $view->with('themeFooterNav', $footer !== [] ? $this->flattenMenuLinks($footer) : [
                ['label' => 'Cart', 'url' => route('storefront.cart')],
            ]);
            $view->with('themeLegalNav', $legal !== [] ? $this->flattenMenuLinks($legal) : [
                ['label' => 'Terms', 'url' => null],
                ['label' => 'Privacy', 'url' => null],
            ]);

            if (! array_key_exists('themeConfig', $view->getData())) {
                $view->with('themeConfig', $this->app->make(ThemeManager::class)->config());
            }

            $discoveryCategories = collect();
            try {
                $discoveryCategories = $this->app->make(ListStorefrontCategories::class)->handle();
            } catch (\Throwable) {
                $discoveryCategories = collect();
            }
            $view->with('discoveryCategories', $discoveryCategories);
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
            icon: 'layout-dashboard',
            sort: 0,
            permission: 'dashboard.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'products',
            label: 'Products',
            group: 'Commerce',
            href: '/admin/products',
            icon: 'package',
            sort: 10,
            permission: 'products.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'categories',
            label: 'Categories',
            group: 'Commerce',
            href: '/admin/categories',
            icon: 'folders',
            sort: 15,
            permission: 'categories.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'orders',
            label: 'Orders',
            group: 'Commerce',
            href: '/admin/orders',
            icon: 'shopping-bag',
            sort: 20,
            permission: 'orders.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'settings',
            label: 'Settings',
            group: 'Configuration',
            href: '/admin/settings',
            icon: 'settings',
            sort: 100,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'currencies',
            label: 'Currencies',
            group: 'Configuration',
            href: '/admin/currencies',
            icon: 'coins',
            sort: 110,
            permission: 'currencies.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'staff',
            label: 'Staff',
            group: 'Administration',
            href: '/admin/staff',
            icon: 'users',
            sort: 200,
            permission: 'staff.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'themes',
            label: 'Themes',
            group: 'Appearance',
            href: '/admin/appearance/themes',
            icon: 'layout-template',
            sort: 300,
            permission: 'theme.view',
        ));
        $admin->navigation(new NavigationItem(
            id: 'theme-customize',
            label: 'Customize',
            group: 'Appearance',
            href: '/admin/appearance/customize',
            icon: 'palette',
            sort: 310,
            permission: 'theme.view',
        ));
        $admin->navigation(new NavigationItem(
            id: 'navigation',
            label: 'Navigation',
            group: 'Appearance',
            href: '/admin/appearance/navigation',
            icon: 'menu',
            sort: 320,
            permission: 'navigation.view',
        ));
        $admin->navigation(new NavigationItem(
            id: 'pages',
            label: 'Pages',
            group: 'Appearance',
            href: '/admin/appearance/pages',
            icon: 'file-text',
            sort: 330,
            permission: 'pages.view',
        ));
    }

    /**
     * @param  list<array{label: string, url: string|null, children?: list<array{label: string, url: string|null}>}>  $items
     * @return list<array{label: string, url: string|null}>
     */
    private function flattenMenuLinks(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $out[] = ['label' => $item['label'], 'url' => $item['url']];
            foreach ($item['children'] ?? [] as $child) {
                $out[] = ['label' => $child['label'], 'url' => $child['url']];
            }
        }

        return $out;
    }

    private function registerPermissions(AdminRegistrar $admin): void
    {
        $admin->permission('dashboard.view', 'View dashboard');
        $admin->permission('products.view', 'View products');
        $admin->permission('products.create', 'Create products');
        $admin->permission('products.update', 'Update products');
        $admin->permission('products.delete', 'Delete products');
        $admin->permission('categories.view', 'View categories');
        $admin->permission('categories.create', 'Create categories');
        $admin->permission('categories.update', 'Update categories');
        $admin->permission('categories.delete', 'Delete categories');
        $admin->permission('orders.view', 'View orders');
        $admin->permission('payments.record', 'Record payments');
        $admin->permission('settings.view', 'View settings');
        $admin->permission('settings.update', 'Update settings');
        $admin->permission('currencies.view', 'View currencies');
        $admin->permission('currencies.create', 'Create currencies');
        $admin->permission('currencies.update', 'Update currencies');
        $admin->permission('staff.view', 'View staff');
        $admin->permission('staff.create', 'Create staff');
        $admin->permission('theme.view', 'View themes');
        $admin->permission('theme.manage', 'Manage themes');
        $admin->permission('pages.view', 'View pages');
        $admin->permission('pages.manage', 'Manage pages');
        $admin->permission('navigation.view', 'View navigation');
        $admin->permission('navigation.manage', 'Manage navigation');
    }

    private function registerSettings(AdminRegistrar $admin): void
    {
        $admin->settingsGroup(new SettingsGroup(
            id: 'general',
            label: 'General',
            permission: 'settings.view',
            sort: 10,
            description: 'Site identity and regional defaults.',
            icon: 'settings',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'branding',
            label: 'Branding',
            permission: 'settings.view',
            sort: 20,
            description: 'Logo and favicon used across Admin and storefront.',
            icon: 'palette',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'store',
            label: 'Store',
            permission: 'settings.view',
            sort: 30,
            description: 'Commerce defaults that do not belong in environment secrets.',
            icon: 'store',
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
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'enable_reviews',
            label: 'Enable product reviews',
            type: 'boolean',
            default: true,
            help: 'When off, review UI is hidden on all product pages. Per-product review content still deferred.',
            sort: 30,
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
