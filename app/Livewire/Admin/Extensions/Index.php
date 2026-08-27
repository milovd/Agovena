<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Extensions;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Extensions\ExtensionCategory;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Agovena\Packages\PackageCatalog;
use App\Agovena\Payments\HealthResult;
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

    public function openSettings(string $extensionId, ExtensionManager $extensions, ExtensionSettingsRepository $settings): void
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
        foreach ($status['manifest']->settings as $definition) {
            $key = $definition['key'];
            $secret = (bool) ($definition['secret'] ?? false);
            $current = $settings->get($extensionId, $key, $definition['default'] ?? '');
            $this->secretConfigured[$key] = $secret && $settings->isConfigured($extensionId, $key);
            $this->settingsForm[$key] = $secret ? '' : $current;
        }
    }

    public function closeSettings(): void
    {
        $this->settingsExtensionId = null;
        $this->settingsForm = [];
        $this->secretConfigured = [];
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
            $settings->set($this->settingsExtensionId, $key, $value, $secret);
        }

        session()->flash('status', __('admin.extensions.flash.settings_saved'));
        $this->closeSettings();
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

    public function render(AdminRegistrar $admin, PackageCatalog $catalog)
    {
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
