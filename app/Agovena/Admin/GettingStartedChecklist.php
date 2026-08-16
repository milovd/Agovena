<?php

declare(strict_types=1);

namespace App\Agovena\Admin;

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

final class GettingStartedChecklist
{
    public const SETTINGS_GROUP = 'admin';

    public const SETTINGS_KEY = 'getting_started_dismissed';

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ModuleManager $modules,
        private readonly ExtensionManager $extensions,
        private readonly ExtensionSettingsRepository $extensionSettings,
    ) {}

    public function dismissed(): bool
    {
        return (bool) $this->settings->get(self::SETTINGS_GROUP, self::SETTINGS_KEY, false);
    }

    public function dismiss(): void
    {
        $this->settings->set(self::SETTINGS_GROUP, self::SETTINGS_KEY, true);
    }

    /**
     * @return list<GettingStartedItem>
     */
    public function items(): array
    {
        if ($this->dismissed()) {
            return [];
        }

        $items = [
            new GettingStartedItem(
                id: 'product',
                labelKey: 'admin.dashboard.getting_started.product',
                href: route('admin.products.create'),
                done: Product::query()->exists(),
                descriptionKey: 'admin.dashboard.getting_started.product_description',
            ),
            new GettingStartedItem(
                id: 'payment',
                labelKey: 'admin.dashboard.getting_started.payment',
                href: route('admin.extensions.index'),
                done: $this->hostedPaymentConfigured(),
                descriptionKey: 'admin.dashboard.getting_started.payment_description',
            ),
        ];

        if ($this->modules->isEnabled('shipping')) {
            $items[] = new GettingStartedItem(
                id: 'shipping',
                labelKey: 'admin.dashboard.getting_started.shipping',
                href: Route::has('admin.shipping.methods')
                    ? route('admin.shipping.methods')
                    : route('admin.modules.index'),
                done: $this->hasConfiguredShippingMethod(),
                descriptionKey: 'admin.dashboard.getting_started.shipping_description',
            );
        }

        if ($this->modules->isEnabled('provisioning')) {
            $items[] = new GettingStartedItem(
                id: 'provisioning',
                labelKey: 'admin.dashboard.getting_started.provisioning',
                href: route('admin.extensions.index'),
                done: $this->extensions->isEnabled('pterodactyl'),
                descriptionKey: 'admin.dashboard.getting_started.provisioning_description',
            );
        }

        if ($this->modules->isEnabled('digital')) {
            $items[] = new GettingStartedItem(
                id: 'downloads',
                labelKey: 'admin.dashboard.getting_started.downloads',
                href: Route::has('admin.digital.assets')
                    ? route('admin.digital.assets')
                    : route('admin.modules.index'),
                done: $this->hasDownloadAssets(),
                descriptionKey: 'admin.dashboard.getting_started.downloads_description',
            );
        }

        if ($this->modules->isEnabled('digital-delivery')) {
            $items[] = new GettingStartedItem(
                id: 'digital_delivery',
                labelKey: 'admin.dashboard.getting_started.digital_delivery',
                href: Route::has('admin.digital-delivery.secrets')
                    ? route('admin.digital-delivery.secrets')
                    : route('admin.modules.index'),
                done: $this->hasDigitalSecrets(),
                descriptionKey: 'admin.dashboard.getting_started.digital_delivery_description',
            );
        }

        $items[] = new GettingStartedItem(
            id: 'theme',
            labelKey: 'admin.dashboard.getting_started.theme',
            href: route('admin.appearance.customize'),
            done: is_string($this->settings->get('branding', 'logo_path'))
                && $this->settings->get('branding', 'logo_path') !== '',
            descriptionKey: 'admin.dashboard.getting_started.theme_description',
        );

        $from = $this->settings->get('mail', 'from_address');
        $items[] = new GettingStartedItem(
            id: 'mail',
            labelKey: 'admin.dashboard.getting_started.mail',
            href: route('admin.settings.edit', ['group' => 'mail']),
            done: is_string($from) && $from !== '',
            descriptionKey: 'admin.dashboard.getting_started.mail_description',
        );

        foreach ($items as $item) {
            if (! $item->done) {
                return $items;
            }
        }

        return [];
    }

    private function hostedPaymentConfigured(): bool
    {
        return ($this->extensions->isEnabled('mollie') && $this->extensionSettings->isConfigured('mollie', 'api_key'))
            || ($this->extensions->isEnabled('stripe') && $this->extensionSettings->isConfigured('stripe', 'secret_key'));
    }

    private function hasConfiguredShippingMethod(): bool
    {
        if (! Schema::hasTable('shipping_methods')) {
            return false;
        }

        return DB::table('shipping_methods')->where('is_active', true)->exists();
    }

    private function hasDownloadAssets(): bool
    {
        if (! Schema::hasTable('digital_assets')) {
            return false;
        }

        return DB::table('digital_assets')->exists();
    }

    private function hasDigitalSecrets(): bool
    {
        if (! Schema::hasTable('digital_secret_items')) {
            return false;
        }

        return DB::table('digital_secret_items')->exists();
    }
}
