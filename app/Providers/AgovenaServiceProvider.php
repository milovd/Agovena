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
use App\Agovena\Cart\TokenCartRepository;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\ListStorefrontCategories;
use App\Agovena\Checkout\CartRequirementComposer;
use App\Agovena\Checkout\Contributors\CoreCheckoutContributor;
use App\Agovena\Checkout\Contributors\CustomPropertyRequirementContributor;
use App\Agovena\Checkout\Contributors\ProductConfigurationContributor;
use App\Agovena\Checkout\Contributors\ShippableRequirementContributor;
use App\Agovena\Checkout\NullShippingQuoteResolver;
use App\Agovena\Checkout\ShippingQuoteResolver;
use App\Agovena\Content\MenuResolver;
use App\Agovena\Customer\AccountOverviewCard;
use App\Agovena\Customer\CustomerAccountNav;
use App\Agovena\Customer\CustomerAccountOverview;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Extensions\RuntimeRegistry;
use App\Agovena\Fulfillment\NullOrderFulfillmentPresenter;
use App\Agovena\Fulfillment\OrderFulfillmentPresenter;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Installation\EnsurePublicStorageLink;
use App\Agovena\Installation\InstallAgovena;
use App\Agovena\Installation\InstallationRequirements;
use App\Agovena\Installation\InstallationState;
use App\Agovena\Invoices\InvoiceDocumentView;
use App\Agovena\Mail\ApplyMailSettings;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Money\CurrencyCatalog;
use App\Agovena\Notifications\MinishlinkPushTransport;
use App\Agovena\Notifications\PushTransport;
use App\Agovena\Packages\ComposerRunner;
use App\Agovena\Packages\GitMonorepoCheckout;
use App\Agovena\Packages\MonorepoCheckout;
use App\Agovena\Packages\PackageMigrationRunner;
use App\Agovena\Packages\ProcessComposerRunner;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Shipping\ShippingCarrierRegistry;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Agovena\Tax\AutomaticTaxRateProvider;
use App\Agovena\Tax\StaticCatalogTaxRateProvider;
use App\Agovena\Tax\VatnodeRemoteTaxRateProvider;
use App\Agovena\Theme\StorefrontBrand;
use App\Agovena\Theme\ThemeManager;
use App\Agovena\Theme\ThemeSurface;
use App\Agovena\Webhooks\WebhookEventSubscriber;
use App\Enums\TicketStatus;
use App\Events\CreditNoteIssued;
use App\Events\OrderCancelled;
use App\Events\OrderCreated;
use App\Events\OrderPaid;
use App\Events\PaymentRecorded;
use App\Events\PlanChangeApplied;
use App\Events\RefundRecorded;
use App\Listeners\ApplyPlanChangeWhenOrderPaid;
use App\Listeners\AttachGuestOrdersWhenCustomerVerified;
use App\Listeners\CaptureAccountBalanceWhenOrderPaid;
use App\Listeners\IssueInvoiceWhenOrderCreated;
use App\Listeners\IssueInvoiceWhenOrderPaid;
use App\Listeners\LogFailedNotification;
use App\Listeners\LogSentMail;
use App\Listeners\ReleaseAccountBalanceWhenOrderCancelled;
use App\Listeners\SendCreditNoteIssuedNotification;
use App\Listeners\SendOrderPlacedNotification;
use App\Listeners\SendPaymentRecordedNotification;
use App\Listeners\SendPlanChangeAppliedNotification;
use App\Listeners\SendRefundProcessedNotification;
use App\Listeners\SettleReferralRewardWhenOrderPaid;
use App\Models\Customer;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AgovenaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminRegistrar::class, InMemoryAdminRegistrar::class);
        $this->app->singleton(CustomerAccountNav::class);
        $this->app->singleton(CustomerAccountOverview::class);
        $this->app->singleton(ProductCapabilityRegistry::class);
        $this->app->singleton(ProductCapabilityManager::class);
        $this->app->singleton(ModuleManager::class);
        $this->app->singleton(PackageMigrationRunner::class);
        $this->app->singleton(ComposerRunner::class, ProcessComposerRunner::class);
        $this->app->singleton(MonorepoCheckout::class, GitMonorepoCheckout::class);
        $this->app->singleton(ExtensionSettingsRepository::class);
        $this->app->singleton(RuntimeRegistry::class);
        $this->app->singleton(PaymentGatewayRegistry::class);
        $this->app->singleton(ProvisionerRegistry::class);
        $this->app->singleton(ShippingCarrierRegistry::class);
        $this->app->singleton(ExtensionManager::class);
        $this->app->singleton(ShippingQuoteResolver::class, NullShippingQuoteResolver::class);
        $this->app->singleton(OrderFulfillmentPresenter::class, NullOrderFulfillmentPresenter::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(InvoiceDocumentView::class);
        $this->app->singleton(SettingsRepository::class);
        $this->app->singleton(PushTransport::class, MinishlinkPushTransport::class);
        $this->app->singleton(CurrencyCatalog::class);
        $this->app->singleton(AutomaticTaxRateProvider::class, function ($app): AutomaticTaxRateProvider {
            $driver = (string) config('agovena.tax.automatic_provider', 'vatnode');

            return match ($driver) {
                'catalog' => $app->make(StaticCatalogTaxRateProvider::class),
                default => $app->make(VatnodeRemoteTaxRateProvider::class),
            };
        });
        $this->app->singleton(InstallationState::class);
        $this->app->singleton(ApplicationSchemaStatus::class, function ($app): ApplicationSchemaStatus {
            return new ApplicationSchemaStatus(
                $app->make('migrator'),
                $app->make(ModuleManager::class),
                $app->make(ExtensionManager::class),
            );
        });
        $this->app->singleton(InstallationRequirements::class);
        $this->app->singleton(EnsurePublicStorageLink::class);
        $this->app->singleton(InstallAgovena::class);
        $this->app->bind(CartRepository::class, function ($app) {
            $request = $app->make('request');
            if ($request->is('api') || $request->is('api/*')) {
                return $app->make(TokenCartRepository::class);
            }

            return $app->make(SessionCartRepository::class);
        });
        $this->app->singleton(CartRequirementComposer::class, function ($app): CartRequirementComposer {
            return new CartRequirementComposer([
                $app->make(CoreCheckoutContributor::class),
                $app->make(ShippableRequirementContributor::class),
                $app->make(ProductConfigurationContributor::class),
                $app->make(CustomPropertyRequirementContributor::class),
            ]);
        });

        $this->mergeConfigFrom(__DIR__.'/../../config/agovena.php', 'agovena');
    }

    public function boot(): void
    {
        Gate::before(static function (User $user, string $ability): ?bool {
            if ($ability !== 'customers.view') {
                return null;
            }

            return $user->hasPermissionTo('customers.view', User::GUARD)
                || $user->hasPermissionTo('users.view', User::GUARD);
        });

        Event::subscribe(WebhookEventSubscriber::class);
        Event::listen(Registered::class, SendEmailVerificationNotification::class);
        Event::listen(Verified::class, AttachGuestOrdersWhenCustomerVerified::class);
        Event::listen(OrderCreated::class, SendOrderPlacedNotification::class);
        Event::listen(OrderCreated::class, IssueInvoiceWhenOrderCreated::class);
        Event::listen(OrderPaid::class, IssueInvoiceWhenOrderPaid::class);
        Event::listen(OrderPaid::class, ApplyPlanChangeWhenOrderPaid::class);
        Event::listen(OrderPaid::class, SettleReferralRewardWhenOrderPaid::class);
        Event::listen(OrderPaid::class, CaptureAccountBalanceWhenOrderPaid::class);
        Event::listen(OrderCancelled::class, ReleaseAccountBalanceWhenOrderCancelled::class);
        Event::listen(PaymentRecorded::class, SendPaymentRecordedNotification::class);
        Event::listen(CreditNoteIssued::class, SendCreditNoteIssuedNotification::class);
        Event::listen(RefundRecorded::class, SendRefundProcessedNotification::class);
        Event::listen(PlanChangeApplied::class, SendPlanChangeAppliedNotification::class);
        Event::listen(MessageSent::class, LogSentMail::class);
        Event::listen(NotificationFailed::class, LogFailedNotification::class);

        $this->app->make(ApplyMailSettings::class)();

        /** @var AdminRegistrar $admin */
        $admin = $this->app->make(AdminRegistrar::class);

        $this->registerNavigation($admin);
        $this->registerPermissions($admin);
        $this->registerSettings($admin);
        $this->registerWidgets($admin);
        $this->registerCustomerAccountCards();

        // Feature tests rebuild the schema after the application boots. Persistent
        // databases still contain the previous test's enabled packages at this
        // point; booting them here would bind real provider clients before fakes.
        if (! $this->app->runningUnitTests()) {
            $this->app->make(ModuleManager::class)->bootEnabled();
            $this->app->make(ExtensionManager::class)->bootEnabled();
        }

        $themes = $this->app->make(ThemeManager::class);
        $theme = $themes->active();
        View::addNamespace('theme', $theme->viewsPath);

        $adminTheme = $themes->themeFor(ThemeSurface::Admin);
        View::prependLocation($adminTheme->viewsPath);

        View::composer(['layouts.admin', 'layouts.admin-guest', 'theme::layouts.admin', 'theme::layouts.admin-guest', 'theme::layouts.storefront', 'theme::layouts.checkout'], function ($view): void {
            $brand = $this->app->make(StorefrontBrand::class);
            $view->with('siteName', $brand->siteName());
            $view->with('brandingLogoUrl', $brand->logoUrl());
            $view->with('brandingFaviconUrl', $brand->faviconUrl());
        });

        View::composer(['theme::layouts.storefront', 'theme::layouts.checkout'], function ($view): void {
            $cartCount = 0;
            try {
                $cartCount = $this->app->make(CartService::class)->itemCount();
            } catch (\Throwable) {
                $cartCount = 0;
            }

            $view->with('cartCount', $cartCount);

            $notificationUnreadCount = 0;
            try {
                if (auth()->check()) {
                    $notificationUnreadCount = UserNotification::query()
                        ->where('user_id', auth()->id())
                        ->whereNull('read_at')
                        ->count();
                }
            } catch (\Throwable) {
                $notificationUnreadCount = 0;
            }

            $view->with('notificationUnreadCount', $notificationUnreadCount);

            /** @var MenuResolver $menus */
            $menus = $this->app->make(MenuResolver::class);
            $main = $menus->handle('header');
            $footer = $menus->handle('footer');
            $legal = $menus->handle('footer_legal');

            $view->with('themeMainNav', $main !== [] ? $this->flattenMenuLinks($main) : [
                ['label' => __('storefront.nav.deals'), 'url' => route('storefront.home').'#catalog'],
                ['label' => __('storefront.nav.about'), 'url' => url('/about')],
            ]);
            $view->with('themeFooterNav', $footer !== [] ? $this->flattenMenuLinks($footer) : [
                ['label' => __('storefront.nav.cart'), 'url' => route('storefront.cart')],
            ]);
            $view->with('themeLegalNav', $legal !== [] ? $this->flattenMenuLinks($legal) : []);
            $view->with(
                'customerAccountNavItems',
                $this->app->make(CustomerAccountNav::class)->items(),
            );

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

            try {
                $preferences = $this->app->make(StorefrontPreferences::class);
                $view->with('storefrontLocales', $preferences->availableLocales());
                $view->with('storefrontLocale', $preferences->locale());
                $view->with('storefrontCurrencies', $preferences->availableCurrencies());
                $view->with('storefrontCurrency', $preferences->currencyCode());
            } catch (\Throwable) {
                $view->with('storefrontLocales', config('agovena.locales', ['en' => 'English']));
                $view->with('storefrontLocale', app()->getLocale());
                $view->with('storefrontCurrencies', collect());
                $view->with('storefrontCurrency', 'EUR');
            }
        });

        View::composer('theme::account.partials.nav', function ($view): void {
            $view->with(
                'customerAccountNavItems',
                $this->app->make(CustomerAccountNav::class)->items(),
            );
        });

        View::composer(['layouts.admin', 'theme::layouts.admin'], function ($view): void {
            if (! array_key_exists('navigation', $view->getData())) {
                /** @var AdminRegistrar $admin */
                $admin = $this->app->make(AdminRegistrar::class);
                $view->with('navigation', $admin->navigationItems());
            }

            try {
                $view->with(
                    'schemaPendingCount',
                    $this->app->make(ApplicationSchemaStatus::class)->pendingCount(),
                );
            } catch (\Throwable) {
                $view->with('schemaPendingCount', 0);
            }
        });
    }

    private function registerNavigation(AdminRegistrar $admin): void
    {
        $admin->navigation(new NavigationItem(
            id: 'dashboard',
            label: 'admin.nav.dashboard',
            group: 'admin.nav_groups.overview',
            href: '/admin',
            icon: 'layout-dashboard',
            sort: 0,
            permission: 'dashboard.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'products',
            label: 'admin.nav.products',
            group: 'admin.nav_groups.catalog',
            href: '/admin/products',
            icon: 'package',
            sort: 10,
            permission: 'products.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'categories',
            label: 'admin.nav.categories',
            group: 'admin.nav_groups.catalog',
            href: '/admin/categories',
            icon: 'folders',
            sort: 15,
            permission: 'categories.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'orders',
            label: 'admin.nav.orders',
            group: 'admin.nav_groups.sales',
            href: '/admin/orders',
            icon: 'shopping-bag',
            sort: 20,
            permission: 'orders.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'invoices',
            label: 'admin.nav.invoices',
            group: 'admin.nav_groups.sales',
            href: '/admin/invoices',
            icon: 'file-text',
            sort: 25,
            permission: 'invoices.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'discounts',
            label: 'admin.nav.discounts',
            group: 'admin.nav_groups.sales',
            href: '/admin/discounts',
            icon: 'file-text',
            sort: 27,
            permission: 'discounts.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'customers',
            label: 'admin.nav.customers',
            group: 'admin.nav_groups.customers',
            href: '/admin/customers',
            icon: 'users',
            sort: 30,
            permission: 'customers.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'customer-properties',
            label: 'admin.nav.customer_properties',
            group: 'admin.nav_groups.customers',
            href: '/admin/customers/properties',
            icon: 'settings',
            sort: 31,
            permission: 'customers.manage',
        ));

        $admin->navigation(new NavigationItem(
            id: 'tickets',
            label: 'admin.nav.tickets',
            group: 'admin.nav_groups.operations',
            href: '/admin/tickets',
            icon: 'file-text',
            sort: 50,
            permission: 'tickets.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'taxes',
            label: 'admin.nav.taxes',
            group: 'admin.nav_groups.system',
            href: '/admin/taxes',
            icon: 'coins',
            sort: 115,
            permission: 'taxes.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'settings',
            label: 'admin.nav.settings',
            group: 'admin.nav_groups.system',
            href: '/admin/settings',
            icon: 'settings',
            sort: 100,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'notification-templates',
            label: 'admin.nav.notifications',
            group: 'admin.nav_groups.operations',
            href: '/admin/notifications',
            icon: 'mail',
            sort: 55,
            permission: 'notifications.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'currencies',
            label: 'admin.nav.currencies',
            group: 'admin.nav_groups.system',
            href: '/admin/currencies',
            icon: 'coins',
            sort: 110,
            permission: 'currencies.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'modules',
            label: 'admin.nav.modules',
            group: 'admin.nav_groups.system',
            href: '/admin/modules',
            icon: 'package',
            sort: 119,
            permission: 'modules.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'extensions',
            label: 'admin.nav.extensions',
            group: 'admin.nav_groups.system',
            href: '/admin/extensions',
            icon: 'package',
            sort: 121,
            permission: 'extensions.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'api-tokens',
            label: 'admin.nav.api_tokens',
            group: 'admin.nav_groups.system',
            href: '/admin/api-tokens',
            icon: 'key',
            sort: 122,
            permission: 'api.tokens',
        ));

        $admin->navigation(new NavigationItem(
            id: 'backups',
            label: 'admin.nav.backups',
            group: 'admin.nav_groups.system',
            href: '/admin/backups',
            icon: 'database',
            sort: 123,
            permission: 'backups.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'roles',
            label: 'admin.nav.roles',
            group: 'admin.nav_groups.system',
            href: '/admin/roles',
            icon: 'shield',
            sort: 210,
            permission: 'roles.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'audit',
            label: 'admin.nav.audit',
            group: 'admin.nav_groups.operations',
            href: '/admin/audit',
            icon: 'file-text',
            sort: 60,
            permission: 'audit.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'email-log',
            label: 'admin.nav.email_log',
            group: 'admin.nav_groups.operations',
            href: '/admin/email-log',
            icon: 'mail',
            sort: 62,
            permission: 'notifications.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'referrals',
            label: 'admin.nav.referrals',
            group: 'admin.nav_groups.sales',
            href: '/admin/referrals',
            icon: 'gift',
            sort: 58,
            permission: 'referrals.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'webhooks',
            label: 'admin.nav.webhooks',
            group: 'admin.nav_groups.operations',
            href: '/admin/webhooks',
            icon: 'repeat',
            sort: 61,
            permission: 'webhooks.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'cron-statistics',
            label: 'admin.nav.cron_statistics',
            group: 'admin.nav_groups.operations',
            href: '/admin/cron-statistics',
            icon: 'calendar',
            sort: 63,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'failed-jobs',
            label: 'admin.nav.failed_jobs',
            group: 'admin.nav_groups.operations',
            href: '/admin/failed-jobs',
            icon: 'circle-alert',
            sort: 64,
            permission: 'jobs.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'updates',
            label: 'admin.nav.updates',
            group: 'admin.nav_groups.system',
            href: '/admin/updates',
            icon: 'repeat',
            sort: 230,
            permission: 'settings.view',
        ));

        $admin->navigation(new NavigationItem(
            id: 'themes',
            label: 'admin.nav.themes',
            group: 'admin.nav_groups.appearance',
            href: '/admin/appearance/themes',
            icon: 'layout-template',
            sort: 300,
            permission: 'theme.view',
        ));
        $admin->navigation(new NavigationItem(
            id: 'theme-customize',
            label: 'admin.nav.customize',
            group: 'admin.nav_groups.appearance',
            href: '/admin/appearance/customize',
            icon: 'palette',
            sort: 310,
            permission: 'theme.view',
        ));
        $admin->navigation(new NavigationItem(
            id: 'navigation',
            label: 'admin.nav.navigation',
            group: 'admin.nav_groups.appearance',
            href: '/admin/appearance/navigation',
            icon: 'menu',
            sort: 320,
            permission: 'navigation.view',
        ));
        $admin->navigation(new NavigationItem(
            id: 'pages',
            label: 'admin.nav.pages',
            group: 'admin.nav_groups.appearance',
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
        foreach ([
            'dashboard.view',
            'products.view',
            'products.create',
            'products.update',
            'products.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'orders.view',
            'orders.cancel',
            'payments.record',
            'payments.refund',
            'invoices.view',
            'invoices.manage',
            'invoices.credit',
            'invoices.void',
            'discounts.view',
            'discounts.manage',
            'taxes.view',
            'taxes.manage',
            'plan-changes.view',
            'plan-changes.manage',
            'customers.view',
            'customers.manage',
            'tickets.view',
            'tickets.manage',
            'audit.view',
            'webhooks.view',
            'webhooks.manage',
            'referrals.view',
            'referrals.manage',
            'settings.view',
            'settings.update',
            'currencies.view',
            'currencies.create',
            'currencies.update',
            'modules.view',
            'modules.manage',
            'extensions.view',
            'extensions.manage',
            'users.view',
            'users.create',
            'users.update',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'theme.view',
            'theme.manage',
            'pages.view',
            'pages.manage',
            'navigation.view',
            'navigation.manage',
            'notifications.view',
            'notifications.manage',
            'jobs.view',
            'jobs.manage',
            'api.tokens',
            'backups.view',
            'backups.manage',
        ] as $ability) {
            $admin->permission($ability, 'admin.permissions.'.$ability);
        }
    }

    private function registerSettings(AdminRegistrar $admin): void
    {
        $admin->settingsGroup(new SettingsGroup(
            id: 'general',
            label: 'admin.settings.groups.general',
            permission: 'settings.view',
            sort: 10,
            description: 'admin.settings.group_help.general',
            icon: 'settings',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'branding',
            label: 'admin.settings.groups.branding',
            permission: 'settings.view',
            sort: 20,
            description: 'admin.settings.group_help.branding',
            icon: 'palette',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'mail',
            label: 'admin.settings.groups.mail',
            permission: 'settings.view',
            sort: 25,
            description: 'admin.settings.group_help.mail',
            icon: 'mail',
        ));
        $admin->settingsGroup(new SettingsGroup(
            id: 'store',
            label: 'admin.settings.groups.store',
            permission: 'settings.view',
            sort: 30,
            description: 'admin.settings.group_help.store',
            icon: 'store',
        ));

        $admin->settingsGroup(new SettingsGroup(
            id: 'auth',
            label: 'admin.settings.groups.auth',
            permission: 'settings.view',
            sort: 28,
            description: 'admin.settings.group_help.auth',
            icon: 'lock',
        ));

        $admin->settingsField(new SettingsField(
            group: 'auth',
            key: 'oauth_google_enabled',
            label: 'admin.settings.fields.oauth_google_enabled',
            type: 'boolean',
            default: (bool) config('services.oauth.google.enabled', false),
            help: 'admin.settings.field_help.oauth_google_enabled',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'auth',
            key: 'oauth_discord_enabled',
            label: 'admin.settings.fields.oauth_discord_enabled',
            type: 'boolean',
            default: (bool) config('services.oauth.discord.enabled', false),
            help: 'admin.settings.field_help.oauth_discord_enabled',
            sort: 20,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'site_name',
            label: 'admin.settings.fields.site_name',
            type: 'string',
            default: config('app.name', 'Agovena'),
            help: 'admin.settings.field_help.site_name',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'locale',
            label: 'admin.settings.fields.locale',
            type: 'select',
            default: config('app.locale', 'en'),
            help: 'admin.settings.locale_help',
            sort: 20,
            options: config('agovena.locales', ['en' => 'English']),
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'timezone',
            label: 'admin.settings.fields.timezone',
            type: 'timezone',
            default: config('app.timezone', 'UTC'),
            sort: 30,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'base_currency',
            label: 'admin.settings.fields.base_currency',
            type: 'currency',
            default: 'EUR',
            help: 'admin.settings.field_help.base_currency',
            sort: 40,
        ));
        $admin->settingsField(new SettingsField(
            group: 'general',
            key: 'auto_currency_conversion',
            label: 'admin.settings.fields.auto_currency_conversion',
            type: 'boolean',
            default: true,
            help: 'admin.settings.field_help.auto_currency_conversion',
            sort: 45,
        ));

        $admin->settingsField(new SettingsField(
            group: 'branding',
            key: 'logo_path',
            label: 'admin.settings.fields.logo_path',
            type: 'image',
            default: null,
            help: 'admin.settings.field_help.logo_path',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'branding',
            key: 'favicon_path',
            label: 'admin.settings.fields.favicon_path',
            type: 'image',
            default: null,
            help: 'admin.settings.field_help.favicon_path',
            sort: 20,
        ));

        $admin->settingsField(new SettingsField(
            group: 'mail',
            key: 'from_name',
            label: 'admin.settings.fields.from_name',
            type: 'string',
            default: '',
            help: 'admin.settings.field_help.from_name',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'mail',
            key: 'from_address',
            label: 'admin.settings.fields.from_address',
            type: 'email',
            default: '',
            help: 'admin.settings.field_help.from_address',
            sort: 20,
        ));
        $admin->settingsField(new SettingsField(
            group: 'mail',
            key: 'reply_to',
            label: 'admin.settings.fields.reply_to',
            type: 'email',
            default: '',
            help: 'admin.settings.field_help.reply_to',
            sort: 30,
        ));

        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'google_places_api_key',
            label: 'admin.settings.fields.google_places_api_key',
            type: 'password',
            default: '',
            help: 'admin.settings.field_help.google_places_api_key',
            sort: 15,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'customer_registration',
            label: 'admin.settings.fields.customer_registration',
            type: 'select',
            default: 'optional',
            options: ['disabled', 'optional', 'required'],
            help: 'admin.settings.field_help.customer_registration',
            sort: 10,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'order_number_prefix',
            label: 'admin.settings.fields.order_number_prefix',
            type: 'string',
            default: 'AGO',
            sort: 20,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'invoice_number_prefix',
            label: 'admin.settings.fields.invoice_number_prefix',
            type: 'string',
            default: 'INV',
            sort: 25,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'credit_note_number_prefix',
            label: 'admin.settings.fields.credit_note_number_prefix',
            type: 'string',
            default: 'CN',
            sort: 26,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'seller_name',
            label: 'admin.settings.fields.seller_name',
            type: 'string',
            default: '',
            help: 'admin.settings.field_help.seller_name',
            sort: 27,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'seller_address',
            label: 'admin.settings.fields.seller_address',
            type: 'text',
            default: '',
            help: 'admin.settings.field_help.seller_address',
            sort: 28,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'unpaid_order_cancel_after_days',
            label: 'admin.settings.fields.unpaid_order_cancel_after_days',
            type: 'integer',
            default: 0,
            help: 'admin.settings.field_help.unpaid_order_cancel_after_days',
            sort: 29,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'subscription_auto_charge',
            label: 'admin.settings.fields.subscription_auto_charge',
            type: 'boolean',
            default: true,
            help: 'admin.settings.field_help.subscription_auto_charge',
            sort: 31,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'subscription_retry_max',
            label: 'admin.settings.fields.subscription_retry_max',
            type: 'integer',
            default: 3,
            help: 'admin.settings.field_help.subscription_retry_max',
            sort: 32,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'subscription_retry_hours',
            label: 'admin.settings.fields.subscription_retry_hours',
            type: 'integer',
            default: 24,
            help: 'admin.settings.field_help.subscription_retry_hours',
            sort: 33,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'subscription_retry_exhausted',
            label: 'admin.settings.fields.subscription_retry_exhausted',
            type: 'select',
            default: 'manual',
            help: 'admin.settings.field_help.subscription_retry_exhausted',
            sort: 34,
            options: ['manual', 'cancel_at_period_end'],
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'enable_reviews',
            label: 'admin.settings.fields.enable_reviews',
            type: 'boolean',
            default: true,
            help: 'admin.settings.field_help.enable_reviews',
            sort: 30,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'tax_enabled',
            label: 'admin.settings.fields.tax_enabled',
            type: 'boolean',
            default: true,
            help: 'admin.settings.field_help.tax_enabled',
            sort: 38,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'automatic_tax_rates',
            label: 'admin.settings.fields.automatic_tax_rates',
            type: 'boolean',
            default: true,
            help: 'admin.settings.field_help.automatic_tax_rates',
            sort: 39,
        ));
        $admin->settingsField(new SettingsField(
            group: 'store',
            key: 'prices_include_tax',
            label: 'admin.settings.fields.prices_include_tax',
            type: 'boolean',
            default: false,
            help: 'admin.settings.field_help.prices_include_tax',
            sort: 40,
        ));
    }

    private function registerWidgets(AdminRegistrar $admin): void
    {
        $admin->widget(new DashboardWidget(
            id: 'commerce-stats',
            label: 'admin.dashboard.widgets.commerce_stats',
            view: 'admin.widgets.commerce-stats',
            permission: 'dashboard.view',
            sort: 10,
        ));
        $admin->widget(new DashboardWidget(
            id: 'recent-orders',
            label: 'admin.dashboard.widgets.recent_orders',
            view: 'admin.widgets.recent-orders',
            permission: 'orders.view',
            sort: 20,
        ));
        $admin->widget(new DashboardWidget(
            id: 'attention',
            label: 'admin.dashboard.widgets.attention',
            view: 'admin.widgets.attention',
            permission: 'dashboard.view',
            sort: 30,
        ));
    }

    private function registerCustomerAccountCards(): void
    {
        $this->app->make(CustomerAccountOverview::class)->register(
            'support-tickets',
            static fn (Customer $customer): AccountOverviewCard => new AccountOverviewCard(
                id: 'support-tickets',
                label: 'customer.account.cards.open_tickets',
                countOrValue: (string) $customer->tickets()
                    ->whereIn('status', [
                        TicketStatus::Open->value,
                        TicketStatus::Pending->value,
                        TicketStatus::Answered->value,
                    ])
                    ->count(),
                routeName: 'customer.tickets.index',
                sort: 50,
            ),
            50,
        );
    }
}
