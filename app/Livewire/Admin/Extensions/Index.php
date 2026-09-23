<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Extensions;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Packages\PackageCatalog;
use App\Agovena\Payments\Contracts\ConfiguresCheckoutMethods;
use App\Agovena\Payments\Contracts\RefreshesCheckoutMethods;
use App\Agovena\Payments\HealthResult;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\PaymentMethodDiscoveryCache;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use App\Enums\PackageKind;
use App\Livewire\Admin\Concerns\InstallsRemotePackages;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;

final class Index extends Component
{
    use AuthorizesRequests;
    use InstallsRemotePackages;

    #[Url(as: 'tab')]
    public string $tab = 'installed';

    public string $category = '';

    public ?string $settingsExtensionId = null;

    /** @var array<string, mixed> */
    public array $settingsForm = [];

    /** @var array<string, bool> */
    public array $secretConfigured = [];

    /** @var list<array{id: string, label: string, icon: ?string}> */
    public array $settingsMethodOptions = [];

    /** @var list<string> */
    public array $settingsMethodSelections = [];

    /** @var list<string> */
    public array $settingsStoredMethodSelections = [];

    /** @var list<string> */
    public array $settingsSecretKeys = [];

    /** @var list<string> */
    public array $settingsConnectionKeys = [];

    public bool $settingsMethodsLoaded = false;

    public bool $settingsConnectionCached = false;

    public ?string $settingsConnectionState = null;

    public string $settingsConnectionMessage = '';

    public function mount(): void
    {
        $this->authorize('extensions.view');

        if (! in_array($this->tab, ['installed', 'available', 'install'], true)) {
            $this->tab = 'installed';
        }
    }

    public function enable(string $extensionId, ExtensionManager $extensions, SyncRegisteredPermissions $sync): void
    {
        $this->authorize('extensions.manage');

        try {
            $extensions->enable($extensionId);
            $sync(force: true);
            session()->flash('status', __('admin.extensions.flash.enabled', ['extension' => $extensionId]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['extension'][0] ?? $e->getMessage());
        }
    }

    public function disable(string $extensionId, ExtensionManager $extensions, SyncRegisteredPermissions $sync): void
    {
        $this->authorize('extensions.manage');

        try {
            $extensions->disable($extensionId);
            $sync(force: true);
            session()->flash('status', __('admin.extensions.flash.disabled', ['extension' => $extensionId]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['extension'][0] ?? $e->getMessage());
        }
    }

    public function install(string $extensionId, ExtensionManager $extensions): void
    {
        $this->authorize('extensions.manage');

        try {
            $extensions->install($extensionId);
            session()->flash('status', __('admin.extensions.flash.installed', ['extension' => $extensionId]));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['extension'][0] ?? $e->getMessage());
        }
    }

    public function openSettings(string $extensionId, ExtensionManager $extensions, ExtensionSettingsRepository $settings, PaymentGatewayRegistry $gateways): void
    {
        $this->authorize('extensions.manage');

        $status = $extensions->status($extensionId);
        if (! $status['enabled']) {
            session()->flash('error', __('admin.extensions.settings_requires_enabled'));

            return;
        }

        if ($status['manifest']->settings === []) {
            session()->flash('error', __('admin.extensions.settings_empty'));

            return;
        }

        $this->settingsExtensionId = $extensionId;
        $this->settingsForm = [];
        $this->secretConfigured = [];
        $this->settingsMethodOptions = [];
        $this->settingsMethodSelections = [];
        $this->settingsStoredMethodSelections = [];
        $this->settingsSecretKeys = [];
        $this->settingsConnectionKeys = [];
        $this->settingsMethodsLoaded = false;
        $this->settingsConnectionCached = false;
        $this->settingsConnectionState = null;
        $this->settingsConnectionMessage = '';
        foreach ($status['manifest']->settings as $definition) {
            $key = $definition['key'];
            $secret = (bool) ($definition['secret'] ?? false);
            if ($secret) {
                $this->settingsSecretKeys[] = $key;
            }
            if (($definition['connection'] ?? false) || ($definition['connection_context'] ?? false)) {
                $this->settingsConnectionKeys[] = $key;
            }
            $current = $settings->get($extensionId, $key, $definition['default'] ?? '');
            $this->secretConfigured[$key] = $secret && $settings->isConfigured($extensionId, $key);
            if (($definition['type'] ?? 'string') === 'payment_methods') {
                $this->settingsStoredMethodSelections = is_string($current)
                    ? array_values(array_filter(array_map('trim', explode(',', $current))))
                    : [];
                $this->settingsForm[$key] = '';

                continue;
            }
            $this->settingsForm[$key] = $secret ? '' : $current;
        }

        if ($this->settingsCredentialsReady($status['manifest']->settings, $settings)) {
            $this->checkSettingsConnection($extensionId, $status['manifest']->settings, $extensions, $settings, $gateways);
        }
    }

    public function closeSettings(): void
    {
        $this->settingsExtensionId = null;
        $this->settingsForm = [];
        $this->secretConfigured = [];
        $this->settingsMethodOptions = [];
        $this->settingsMethodSelections = [];
        $this->settingsStoredMethodSelections = [];
        $this->settingsSecretKeys = [];
        $this->settingsConnectionKeys = [];
        $this->settingsMethodsLoaded = false;
        $this->settingsConnectionCached = false;
        $this->settingsConnectionState = null;
        $this->settingsConnectionMessage = '';
    }

    public function saveSettings(ExtensionManager $extensions, ExtensionSettingsRepository $settings): void
    {
        $this->authorize('extensions.manage');
        if ($this->settingsExtensionId === null) {
            return;
        }

        $manifest = $extensions->manifest($this->settingsExtensionId);
        if ($manifest === null) {
            return;
        }

        if ($this->settingsTouchSecrets($manifest->settings) && ! $this->requireRecentPassword('saveSettings')) {
            return;
        }

        foreach ($manifest->settings as $definition) {
            $key = $definition['key'];
            $secret = (bool) ($definition['secret'] ?? false);
            $value = $this->settingsForm[$key] ?? null;
            if ($secret && ($value === null || $value === '')) {
                continue;
            }
            if (($definition['type'] ?? 'string') === 'payment_methods') {
                if (! $this->settingsMethodsLoaded || $this->settingsMethodOptions === []) {
                    session()->flash('error', __('admin.extensions.settings_methods_unavailable'));

                    return;
                }
                $availableIds = array_column($this->settingsMethodOptions, 'id');
                $selected = array_values(array_intersect($this->settingsMethodSelections, $availableIds));
                if ($selected === []) {
                    session()->flash('error', __('admin.extensions.settings_methods_required'));

                    return;
                }
                $value = implode(',', array_values(array_unique($selected)));
            }
            $settings->set($this->settingsExtensionId, $key, $value, $secret);
        }

        session()->flash('status', __('admin.extensions.flash.settings_saved'));
        $this->closeSettings();
    }

    public function updatedSettingsForm(mixed $value, string $key): void
    {
        $key = str_contains($key, '.') ? substr($key, strrpos($key, '.') + 1) : $key;
        if (! in_array($key, $this->settingsConnectionKeys, true)
            && ! in_array($key, $this->settingsSecretKeys, true)) {
            return;
        }

        $this->settingsMethodOptions = [];
        $this->settingsMethodSelections = [];
        $this->settingsMethodsLoaded = false;
        $this->settingsConnectionCached = false;
        $this->settingsConnectionState = null;
        $this->settingsConnectionMessage = '';

        if ($this->settingsExtensionId === null) {
            return;
        }

        $extensions = app(ExtensionManager::class);
        $manifest = $extensions->manifest($this->settingsExtensionId);
        $settings = app(ExtensionSettingsRepository::class);
        if ($manifest !== null && $this->settingsCredentialsReady($manifest->settings, $settings)) {
            $this->checkSettingsConnection(
                $this->settingsExtensionId,
                $manifest->settings,
                $extensions,
                $settings,
                app(PaymentGatewayRegistry::class),
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function settingsTouchSecrets(array $definitions): bool
    {
        foreach ($definitions as $definition) {
            if (! (bool) ($definition['secret'] ?? false)) {
                continue;
            }
            $value = $this->settingsForm[$definition['key']] ?? null;
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    public function runHealth(string $extensionId, ExtensionManager $extensions): void
    {
        $this->authorize('extensions.manage');
        $context = $extensions->context($extensionId);
        $callback = $context?->healthCallback();
        if ($callback === null) {
            session()->flash('error', __('admin.extensions.health.unavailable'));

            return;
        }

        /** @var HealthResult $result */
        $result = $callback();
        if ($result->ok) {
            session()->flash('status', __('admin.extensions.health.ok', ['message' => $result->message]));
        } else {
            session()->flash('error', __('admin.extensions.health.fail', ['message' => $result->message]));
        }
    }

    public function refreshPaymentMethods(ExtensionManager $extensions, ExtensionSettingsRepository $settings, PaymentGatewayRegistry $gateways): void
    {
        $this->authorize('extensions.manage');
        if ($this->settingsExtensionId === null) {
            return;
        }

        $extensionId = $this->settingsExtensionId;
        $manifest = $extensions->manifest($extensionId);
        if ($manifest === null) {
            $this->settingsConnectionState = 'error';
            $this->settingsConnectionMessage = __('admin.extensions.health.unavailable');

            return;
        }

        $this->settingsMethodOptions = [];
        $this->settingsMethodSelections = [];
        $this->settingsMethodsLoaded = false;
        $this->settingsConnectionCached = false;
        $this->settingsConnectionState = null;
        $this->settingsConnectionMessage = '';

        if (! $this->settingsCredentialsReady($manifest->settings, $settings)) {
            $this->settingsConnectionState = 'error';
            $this->settingsConnectionMessage = __('admin.extensions.settings_credentials_required');

            return;
        }

        $this->checkSettingsConnection(
            $extensionId,
            $manifest->settings,
            $extensions,
            $settings,
            $gateways,
            true,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function checkSettingsConnection(
        string $extensionId,
        array $definitions,
        ExtensionManager $extensions,
        ExtensionSettingsRepository $settings,
        PaymentGatewayRegistry $gateways,
        bool $force = false,
    ): void {
        $context = $extensions->context($extensionId);
        $callback = $context?->healthCallback();
        $gateway = $gateways->get($extensionId);
        $hasMethodSettings = $this->settingsHasPaymentMethods($definitions);
        if ($callback === null || ($hasMethodSettings && ! $gateway instanceof ConfiguresCheckoutMethods)) {
            $this->settingsConnectionState = 'error';
            $this->settingsConnectionMessage = __('admin.extensions.health.unavailable');

            return;
        }

        $this->settingsConnectionCached = false;
        $discoveryCache = app(PaymentMethodDiscoveryCache::class);
        $fingerprint = $discoveryCache->fingerprint($extensionId, $definitions, $this->settingsForm, $settings);
        if ($force) {
            $discoveryCache->forget($extensionId, $fingerprint);
        }
        if (! $force) {
            $cachedMethods = $discoveryCache->get($extensionId, $fingerprint);
            if ($cachedMethods !== null) {
                $this->settingsConnectionCached = true;
                if (! $hasMethodSettings) {
                    $this->settingsConnectionState = 'success';
                    $this->settingsConnectionMessage = __('admin.extensions.settings_connection_cached_without_methods');

                    return;
                }

                $this->settingsMethodOptions = $cachedMethods;
                if (! $this->applySettingsMethodOptions()) {
                    $this->settingsConnectionState = 'error';
                    $this->settingsConnectionMessage = __('admin.extensions.settings_methods_unavailable');

                    return;
                }

                $this->settingsConnectionState = 'success';
                $this->settingsConnectionMessage = __('admin.extensions.settings_connection_cached', [
                    'count' => count($this->settingsMethodOptions),
                ]);

                return;
            }
        }

        $snapshot = $settings->snapshot($extensionId);
        try {
            $this->persistNonMethodSettings($extensionId, $definitions, $settings);

            /** @var HealthResult $result */
            $result = $callback();
            if (! $result->ok) {
                $this->settingsConnectionState = 'error';
                $this->settingsConnectionMessage = __('admin.extensions.health.fail', ['message' => $result->message]);

                return;
            }

            if (! $hasMethodSettings) {
                $discoveryCache->put($extensionId, $fingerprint, []);
                $this->settingsConnectionState = 'success';
                $this->settingsConnectionMessage = __('admin.extensions.settings_connection_ok_without_methods');

                return;
            }

            $this->settingsMethodOptions = $force && $gateway instanceof RefreshesCheckoutMethods
                ? $gateway->refreshConfigurableCheckoutMethods()
                : $gateway->configurableCheckoutMethods();
            if (! $this->applySettingsMethodOptions()) {
                $this->settingsConnectionState = 'error';
                $this->settingsConnectionMessage = __('admin.extensions.settings_methods_unavailable');

                return;
            }

            $discoveryCache->put($extensionId, $fingerprint, $this->settingsMethodOptions);
            $this->settingsConnectionState = 'success';
            $this->settingsConnectionMessage = __('admin.extensions.settings_connection_ok', [
                'count' => count($this->settingsMethodOptions),
            ]);
        } catch (\Throwable) {
            $this->settingsConnectionState = 'error';
            $this->settingsConnectionMessage = __('admin.extensions.settings_connection_failed');
        } finally {
            $settings->restore($extensionId, $snapshot);
        }
    }

    private function applySettingsMethodOptions(): bool
    {
        if ($this->settingsMethodOptions === []) {
            return false;
        }

        $availableIds = array_column($this->settingsMethodOptions, 'id');
        $requested = $this->settingsMethodSelections !== []
            ? $this->settingsMethodSelections
            : $this->settingsStoredMethodSelections;
        $selected = array_values(array_intersect($requested, $availableIds));
        $this->settingsMethodSelections = $selected !== [] ? $selected : $availableIds;
        $this->settingsMethodsLoaded = true;

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function settingsHasPaymentMethods(array $definitions): bool
    {
        foreach ($definitions as $definition) {
            if (($definition['type'] ?? 'string') === 'payment_methods') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function settingsCredentialsReady(array $definitions, ExtensionSettingsRepository $settings): bool
    {
        $connectionSettings = array_values(array_filter(
            $definitions,
            static fn (array $definition): bool => (bool) ($definition['connection'] ?? false),
        ));
        if ($connectionSettings === []) {
            $connectionSettings = array_values(array_filter(
                $definitions,
                static fn (array $definition): bool => (bool) ($definition['secret'] ?? false)
                    && (bool) ($definition['required'] ?? false),
            ));
        }
        if ($connectionSettings === []) {
            $connectionSettings = array_values(array_filter(
                $definitions,
                static fn (array $definition): bool => (bool) ($definition['secret'] ?? false),
            ));
        }

        foreach ($connectionSettings as $definition) {
            $key = (string) $definition['key'];
            $value = $this->settingsForm[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                continue;
            }
            if (($this->secretConfigured[$key] ?? false) && $settings->isConfigured($this->settingsExtensionId ?? '', $key)) {
                continue;
            }
            if (! ($definition['secret'] ?? false)
                && is_scalar($settings->get($this->settingsExtensionId ?? '', $key, null))
                && trim((string) $settings->get($this->settingsExtensionId ?? '', $key, '')) !== '') {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     */
    private function persistNonMethodSettings(string $extensionId, array $definitions, ExtensionSettingsRepository $settings): void
    {
        foreach ($definitions as $definition) {
            if (($definition['type'] ?? 'string') === 'payment_methods') {
                continue;
            }

            $key = (string) $definition['key'];
            $secret = (bool) ($definition['secret'] ?? false);
            $value = $this->settingsForm[$key] ?? null;
            if ($secret && ($value === null || $value === '')) {
                continue;
            }

            $settings->set($extensionId, $key, $value, $secret);
        }
    }

    public function render(AdminRegistrar $admin, PackageCatalog $catalog)
    {
        $this->authorize('extensions.view');
        $groups = $this->orderGroups($this->groupExtensions($catalog->extensions()));

        return view('livewire.admin.extensions.index', [
            'groups' => $groups,
            'installedGroups' => $this->filterGroups($groups, fn (array $row): bool => $row['installed']),
            'availableGroups' => $this->filterGroups($groups, fn (array $row): bool => ! $row['installed']),
            'categories' => ExtensionCategory::cases(),
            'settingsExtensionId' => $this->settingsExtensionId,
            'tabs' => [
                'installed' => __('admin.extensions.tabs.installed'),
                'available' => __('admin.extensions.tabs.available'),
                'install' => __('admin.extensions.tabs.install'),
            ],
        ])->layout('layouts.admin', [
            'title' => __('admin.extensions.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, list<array<string, mixed>>>
     */
    private function groupExtensions(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            if ($this->category !== '' && $row['manifest']->category->value !== $this->category) {
                continue;
            }
            $group = $row['manifest']->category->value;
            $grouped[$group][] = $row;
        }

        return $grouped;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $grouped
     * @return array<string, list<array<string, mixed>>>
     */
    private function orderGroups(array $grouped): array
    {
        $order = [
            ExtensionCategory::PaymentGateway->value,
            ExtensionCategory::Provisioning->value,
            ExtensionCategory::Shipping->value,
            ExtensionCategory::Tax->value,
            ExtensionCategory::Notifications->value,
            ExtensionCategory::Authentication->value,
            ExtensionCategory::Storage->value,
            ExtensionCategory::Analytics->value,
            ExtensionCategory::Domain->value,
            ExtensionCategory::Other->value,
        ];
        $groups = [];
        foreach ($order as $group) {
            if (isset($grouped[$group])) {
                $groups[$group] = $grouped[$group];
                unset($grouped[$group]);
            }
        }
        foreach ($grouped as $group => $rows) {
            $groups[$group] = $rows;
        }

        return $groups;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $groups
     * @param  callable(array<string, mixed>): bool  $predicate
     * @return array<string, list<array<string, mixed>>>
     */
    private function filterGroups(array $groups, callable $predicate): array
    {
        $filtered = [];
        foreach ($groups as $group => $rows) {
            $items = array_values(array_filter($rows, $predicate));
            if ($items !== []) {
                $filtered[$group] = $items;
            }
        }

        return $filtered;
    }

    protected function packageKind(): PackageKind
    {
        return PackageKind::Extension;
    }

    protected function packageManagePermission(): string
    {
        return 'extensions.manage';
    }
}
