<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\CreateProduct;
use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Modules\ModuleManager;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisionedProducts;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisioningServers;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\ProvisioningServer;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Create extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public string $subtitle = '';

    public string $slug = '';

    public string $sku = '';

    public string $description = '';

    /** @var list<array{label: string, value: string}> */
    public array $specRows = [
        ['label' => '', 'value' => ''],
    ];

    public bool $show_details = true;

    public bool $show_specifications = true;

    public string $status = 'draft';

    public string $price = '0.00';

    public string $currency = 'EUR';

    public ?int $category_id = null;

    public bool $configureProvisioning = false;

    public string $providerKey = '';

    public ?int $provisioningServerId = null;

    /** @var array<string, mixed> */
    public array $providerSettings = [];

    public function mount(): void
    {
        $this->authorize('products.create');

        $defaultCurrency = Currency::query()->where('is_active', true)->orderBy('code')->value('code');
        if (is_string($defaultCurrency) && $defaultCurrency !== '') {
            $this->currency = $defaultCurrency;
        }

        $this->initializeProviderSettings();
    }

    public function updatedConfigureProvisioning(bool $enabled): void
    {
        if ($enabled) {
            $this->initializeProviderSettings();
        }
    }

    public function updatedProvisioningServerId(?int $serverId): void
    {
        if ($serverId === null) {
            return;
        }
        $server = ProvisioningServer::query()->where('is_active', true)->find($serverId);
        if ($server !== null) {
            $this->providerKey = $server->provider_key;
            $this->initializeProviderSettings();
        }
    }

    public function addSpecRow(): void
    {
        $this->specRows[] = ['label' => '', 'value' => ''];
    }

    public function removeSpecRow(int $index): void
    {
        unset($this->specRows[$index]);
        $this->specRows = array_values($this->specRows);
        if ($this->specRows === []) {
            $this->specRows = [['label' => '', 'value' => '']];
        }
    }

    public function save(CreateProduct $create, ProductCapabilityManager $capabilities): void
    {
        $this->authorize('products.create');

        $currencyRule = Currency::query()->where('is_active', true)->exists()
            ? Rule::exists('currencies', 'code')->where('is_active', true)
            : ['string', 'size:3'];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'sku' => ['nullable', 'string', 'max:64', 'unique:products,sku'],
            'description' => ['nullable', 'string'],
            'specRows' => ['array'],
            'specRows.*.label' => ['nullable', 'string', 'max:120'],
            'specRows.*.value' => ['nullable', 'string', 'max:255'],
            'show_details' => ['boolean'],
            'show_specifications' => ['boolean'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price' => ['required', 'string', 'max:20'],
            'currency' => ['required', $currencyRule],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'configureProvisioning' => ['boolean'],
            'provisioningServerId' => ['nullable', 'integer'],
            'providerSettings' => ['array'],
        ];

        if ($this->configureProvisioning && $this->canConfigureProvisioning()) {
            $rules['provisioningServerId'] = [
                'required',
                'integer',
                Rule::exists('provisioning_servers', 'id')->where('provider_key', $this->providerKey)->where('is_active', true),
            ];
            foreach ($this->providerSettingDefinitions() as $definition) {
                $fieldRules = [$definition->required ? 'required' : 'nullable'];
                $fieldRules[] = $definition->type === 'boolean' ? 'boolean' : 'string';
                if ($definition->type !== 'boolean') {
                    $fieldRules[] = 'max:10000';
                }
                $rules['providerSettings.'.$definition->key] = $fieldRules;
            }
        }

        $data = $this->validate($rules);

        try {
            $priceAmount = MoneyFormatter::minorFromMajorInput($data['price'], $data['currency']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => $e->getMessage(),
            ]);
        }

        $product = DB::transaction(function () use ($create, $capabilities, $data, $priceAmount) {
            $product = $create->handle([
                'name' => $data['name'],
                'subtitle' => $data['subtitle'] ?: null,
                'slug' => $data['slug'] ?: null,
                'sku' => $data['sku'] ?: null,
                'description' => $data['description'] ?: null,
                'specifications' => $data['specRows'],
                'show_details' => (bool) $data['show_details'],
                'show_specifications' => (bool) $data['show_specifications'],
                'status' => $data['status'],
                'price_amount' => $priceAmount,
                'currency' => $data['currency'],
                'category_id' => $data['category_id'],
            ]);

            if ($this->configureProvisioning && $this->canConfigureProvisioning()) {
                $settings = [];
                foreach ($this->providerSettingDefinitions() as $definition) {
                    $settings[$definition->key] = $data['providerSettings'][$definition->key]
                        ?? $definition->default
                        ?? '';
                }

                $capabilities->enable($product, 'provisionable', [
                    'server_id' => $data['provisioningServerId'],
                    'provider_key' => $this->providerKey,
                    'provider_settings' => $settings,
                ]);
            }

            return $product;
        });

        session()->flash('status', __('admin.products.flash.created_configure'));

        $this->redirect(route('admin.products.edit', $product), navigate: true);
    }

    private function canConfigureProvisioning(): bool
    {
        if (! app(ModuleManager::class)->isEnabled('provisioning')) {
            return false;
        }

        foreach (app(ProvisionerRegistry::class)->all() as $provisioner) {
            if ($provisioner instanceof ConfiguresProvisioningServers) {
                return true;
            }
        }

        return false;
    }

    /** @return list<ExtensionSettingDefinition> */
    private function providerSettingDefinitions(): array
    {
        if (! $this->canConfigureProvisioning()) {
            return [];
        }

        $provisioner = app(ProvisionerRegistry::class)->get($this->providerKey);

        return $provisioner instanceof ConfiguresProvisionedProducts
            ? $provisioner->productSettings()
            : [];
    }

    private function initializeProviderSettings(): void
    {
        if (! $this->canConfigureProvisioning()) {
            $this->providerSettings = [];

            return;
        }

        foreach ($this->providerSettingDefinitions() as $definition) {
            $this->providerSettings[$definition->key] ??= $definition->default ?? '';
        }
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'mode' => 'create',
            'galleryImages' => collect(),
            'availableCapabilities' => app(ProductCapabilityRegistry::class)->available(),
            'canConfigureProvisioning' => $this->canConfigureProvisioning(),
            'providerSettingDefinitions' => $this->providerSettingDefinitions(),
            'provisioningServers' => Schema::hasTable('provisioning_servers')
                ? ProvisioningServer::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get()
                : collect(),
            'productTabs' => [],
        ])->layout('layouts.admin', [
            'title' => __('admin.products.form.create_title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
