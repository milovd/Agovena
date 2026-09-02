<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\Contracts\ProductStock;
use App\Agovena\Catalog\DeleteProduct;
use App\Agovena\Catalog\SyncProductCurrencyPrices;
use App\Agovena\Catalog\UpdateProduct;
use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisionedProducts;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProvisioningServer;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

final class Edit extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Product $product;

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

    /** @var array<string, string> Major-unit overrides keyed by currency code (empty = no override). */
    public array $currencyPrices = [];

    public ?int $category_id = null;

    /** @var list<TemporaryUploadedFile>|TemporaryUploadedFile|null */
    public $uploads = null;

    public bool $confirmingDelete = false;

    /** @var array<string, bool> */
    public array $capabilityEnabled = [];

    public string $sellingPreset = '';

    public bool $hostedServiceSubscription = false;

    public int $stockQuantity = 0;

    public int $weightGrams = 0;

    public string $subscriptionInterval = 'month';

    public int $subscriptionIntervalCount = 1;

    public int $subscriptionTrialDays = 0;

    public string $providerKey = '';

    public ?int $provisioningServerId = null;

    /** @var array<string, mixed> */
    public array $providerSettings = [];

    public string $domainRegistrarKey = '';

    public string $domainDnsProviderKey = '';

    public string $domainName = '';

    public bool $domainAutoRenew = false;

    public int $domainYears = 1;

    public string $digitalSecretSource = 'pool';

    public function mount(Product $product): void
    {
        $this->authorize('products.update');

        $this->product = $product->load(['images', 'capabilities', 'currencyPrices']);
        $this->name = $product->name;
        $this->subtitle = (string) $product->subtitle;
        $this->slug = $product->slug;
        $this->sku = (string) $product->sku;
        $this->description = (string) $product->description;
        $this->show_details = (bool) $product->show_details;
        $this->show_specifications = (bool) $product->show_specifications;
        $this->status = $product->status->value;
        $this->price = MoneyFormatter::majorInputFromMinor($product->price_amount, $product->currency);
        $this->currency = $product->currency;
        $this->category_id = $product->category_id;
        $this->currencyPrices = $this->emptyCurrencyPriceInputs($product->currency);
        foreach ($product->currencyPrices as $row) {
            $this->currencyPrices[strtoupper($row->currency)] = MoneyFormatter::majorInputFromMinor(
                $row->price_amount,
                $row->currency,
            );
        }

        /** @var list<array{label: string, value: string}> $specs */
        $specs = $product->specifications ?? [];
        $this->specRows = $specs === []
            ? [['label' => '', 'value' => '']]
            : array_map(static fn (array $row): array => [
                'label' => $row['label'],
                'value' => $row['value'],
            ], $specs);

        foreach ($product->capabilities as $row) {
            $this->capabilityEnabled[$row->capability] = true;
            if ($row->capability === 'shippable') {
                $this->weightGrams = (int) (($row->config['weight_grams'] ?? 0));
            }
            if ($row->capability === 'subscribable') {
                $this->subscriptionInterval = (string) ($row->config['interval'] ?? 'month');
                $this->subscriptionIntervalCount = max(1, (int) ($row->config['interval_count'] ?? 1));
                $this->subscriptionTrialDays = max(0, (int) ($row->config['trial_days'] ?? 0));
            }
            if ($row->capability === 'provisionable') {
                $this->providerKey = (string) ($row->config['provider_key'] ?? '');
                $serverId = $row->config['server_id'] ?? null;
                $this->provisioningServerId = is_numeric($serverId) ? (int) $serverId : null;
                $settings = $row->config['provider_settings'] ?? [];
                $this->providerSettings = is_array($settings) ? $settings : [];
            }
            if ($row->capability === 'domain_registration') {
                $this->domainRegistrarKey = (string) ($row->config['registrar_key'] ?? '');
                $this->domainDnsProviderKey = (string) ($row->config['dns_provider_key'] ?? '');
                $this->domainName = (string) ($row->config['domain_name'] ?? '');
                $this->domainAutoRenew = (bool) ($row->config['auto_renew'] ?? false);
                $this->domainYears = max(1, (int) ($row->config['years'] ?? 1));
            }
            if ($row->capability === 'digital_secret') {
                $source = (string) ($row->config['source'] ?? 'pool');
                $this->digitalSecretSource = in_array($source, ['pool', 'manual', 'provider'], true)
                    ? $source
                    : 'pool';
            }
        }

        if (app()->bound(ProductStock::class)) {
            $this->stockQuantity = app(ProductStock::class)
                ->quantityFor($product);
        }
    }

    public function updatedCurrency(string $value): void
    {
        $this->currencyPrices = $this->emptyCurrencyPriceInputs($value);
        foreach ($this->product->currencyPrices as $row) {
            $code = strtoupper($row->currency);
            if ($code === strtoupper($value)) {
                continue;
            }
            $this->currencyPrices[$code] = MoneyFormatter::majorInputFromMinor(
                $row->price_amount,
                $row->currency,
            );
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

    public function applyPreset(string $preset): void
    {
        $available = collect(app(ProductCapabilityRegistry::class)->available())
            ->pluck('key')
            ->all();

        if ($preset === 'simple') {
            foreach (array_keys($this->capabilityEnabled) as $key) {
                $this->capabilityEnabled[$key] = false;
            }
            foreach ($available as $key) {
                $this->capabilityEnabled[$key] = false;
            }
        }

        $requested = match ($preset) {
            'physical' => ['physical', 'inventory', 'shippable'],
            'digital' => ['digital_secret'],
            'downloadable' => ['digital'],
            'subscription' => ['subscribable'],
            'hosted_service' => $this->hostedServiceSubscription
                ? ['provisionable', 'subscribable']
                : ['provisionable'],
            'event_ticket' => ['event_ticket'],
            default => [],
        };

        foreach ($requested as $key) {
            if (in_array($key, $available, true)) {
                $this->capabilityEnabled[$key] = true;
            }
        }

        $this->sellingPreset = in_array($preset, [
            'simple',
            'physical',
            'digital',
            'downloadable',
            'subscription',
            'hosted_service',
            'event_ticket',
        ], true) ? $preset : 'simple';
    }

    public function updatedHostedServiceSubscription(): void
    {
        if ($this->sellingPreset === 'hosted_service') {
            $this->applyPreset('hosted_service');
        }
    }

    public function updatedUploads(): void
    {
        $this->authorize('products.update');

        $this->validate([
            'uploads' => ['required'],
            'uploads.*' => ['image', 'mimes:jpeg,jpg,png,webp,gif', 'max:4096'],
        ]);

        $files = is_array($this->uploads) ? $this->uploads : [$this->uploads];
        $sort = (int) $this->product->images()->max('sort');

        foreach ($files as $file) {
            if (! $file instanceof TemporaryUploadedFile) {
                continue;
            }
            $path = $file->store('products/'.$this->product->id, 'public');
            $sort++;
            ProductImage::query()->create([
                'product_id' => $this->product->id,
                'path' => $path,
                'sort' => $sort,
            ]);

            if (blank($this->product->image_path)) {
                $this->product->forceFill(['image_path' => $path])->save();
            }
        }

        $this->uploads = null;
        $this->product->refresh()->load('images');
        session()->flash('status', __('admin.products.flash.photos_uploaded'));
    }

    public function setPrimaryImage(int $imageId): void
    {
        $this->authorize('products.update');

        $image = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        $this->product->forceFill(['image_path' => $image->path])->save();
        $this->product->refresh()->load('images');
        session()->flash('status', __('admin.products.flash.primary_updated'));
    }

    public function moveImage(int $imageId, string $direction): void
    {
        $this->authorize('products.update');

        $image = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        $swap = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->when(
                $direction === 'up',
                fn ($q) => $q->where('sort', '<', $image->sort)->orderByDesc('sort'),
                fn ($q) => $q->where('sort', '>', $image->sort)->orderBy('sort'),
            )
            ->first();

        if ($swap === null) {
            return;
        }

        DB::transaction(function () use ($image, $swap): void {
            $current = $image->sort;
            $image->forceFill(['sort' => $swap->sort])->save();
            $swap->forceFill(['sort' => $current])->save();
        });

        $this->product->refresh()->load('images');
    }

    public function removeImage(int $imageId): void
    {
        $this->authorize('products.update');

        $image = ProductImage::query()
            ->where('product_id', $this->product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        Storage::disk('public')->delete($image->path);
        $wasPrimary = $this->product->image_path === $image->path;
        $image->delete();

        if ($wasPrimary) {
            $next = $this->product->images()->orderBy('sort')->first();
            $this->product->forceFill(['image_path' => $next?->path])->save();
        }

        $this->product->refresh()->load('images');
        session()->flash('status', __('admin.products.flash.photo_removed'));
    }

    public function save(UpdateProduct $update, SyncProductCurrencyPrices $syncPrices): void
    {
        $this->authorize('products.update');

        $currencyRule = Currency::query()->where('is_active', true)->exists()
            ? Rule::exists('currencies', 'code')->where('is_active', true)
            : ['string', 'size:3'];

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->product->id)],
            'sku' => ['nullable', 'string', 'max:64', Rule::unique('products', 'sku')->ignore($this->product->id)],
            'description' => ['nullable', 'string'],
            'specRows' => ['array'],
            'specRows.*.label' => ['nullable', 'string', 'max:120'],
            'specRows.*.value' => ['nullable', 'string', 'max:255'],
            'show_details' => ['boolean'],
            'show_specifications' => ['boolean'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price' => ['required', 'string', 'max:20'],
            'currency' => ['required', $currencyRule],
            'currencyPrices' => ['array'],
            'currencyPrices.*' => ['nullable', 'string', 'max:20'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        try {
            $priceAmount = MoneyFormatter::minorFromMajorInput($data['price'], $data['currency']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => $e->getMessage(),
            ]);
        }

        $overrideMinors = [];
        foreach ($data['currencyPrices'] as $code => $major) {
            $code = strtoupper((string) $code);
            $major = trim((string) $major);
            if ($major === '' || $code === strtoupper($data['currency'])) {
                continue;
            }
            try {
                $overrideMinors[$code] = MoneyFormatter::minorFromMajorInput($major, $code);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'currencyPrices.'.$code => $e->getMessage(),
                ]);
            }
        }

        $update->handle($this->product, [
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

        $syncPrices->handle($this->product->fresh(), $overrideMinors);

        $this->product->refresh()->load(['images', 'currencyPrices']);
        session()->flash('status', __('admin.products.flash.updated'));
    }

    public function saveCapabilities(ProductCapabilityManager $capabilities, ProductCapabilityRegistry $registry): void
    {
        $this->authorize('products.update');

        $this->product->load('capabilities');

        $existingCapabilities = $this->product->capabilities
            ->pluck('capability')
            ->all();
        $desired = array_keys(array_filter($this->capabilityEnabled));
        $available = collect($registry->available())->keyBy(static fn ($d) => $d->key);
        $unavailableExisting = array_values(array_diff($existingCapabilities, $available->keys()->all()));
        $desired = array_values(array_unique(array_merge($desired, $unavailableExisting)));

        if (
            in_array('provisionable', $desired, true)
            && $available->has('provisionable')
            && $this->providerKey !== ''
        ) {
            $rules = $this->providerSettingRules();
            $rules['provisioningServerId'] = [
                'required',
                'integer',
                Rule::exists('provisioning_servers', 'id')->where('provider_key', $this->providerKey)->where('is_active', true),
            ];
            $this->validate($rules);
        }

        if (in_array('domain_registration', $desired, true) && $available->has('domain_registration')) {
            $this->validateDomainProviderSelections();
        }

        $managedDesired = array_values(array_filter(
            $desired,
            static function (string $key) use ($desired, $available): bool {
                $definition = $available->get($key);
                if ($definition === null) {
                    return false;
                }
                foreach ($definition->requires as $required) {
                    if (! in_array($required, $desired, true)) {
                        return false;
                    }
                }

                return true;
            },
        ));
        $desired = array_values(array_unique(array_merge($managedDesired, $unavailableExisting)));

        // Enable required dependencies first (stable order by requirement depth).
        $ordered = $desired;
        usort($ordered, static function (string $a, string $b) use ($available): int {
            $defA = $available->get($a);
            $defB = $available->get($b);
            $ra = $defA instanceof ProductCapabilityDefinition
                ? count($defA->requires)
                : 0;
            $rb = $defB instanceof ProductCapabilityDefinition
                ? count($defB->requires)
                : 0;

            return $ra <=> $rb;
        });

        foreach ($ordered as $key) {
            if (! $available->has($key)) {
                continue;
            }
            $config = [];
            if ($key === 'shippable') {
                $config['weight_grams'] = max(0, $this->weightGrams);
            }
            if ($key === 'subscribable') {
                $config = [
                    'interval' => in_array($this->subscriptionInterval, ['day', 'week', 'month', 'year'], true)
                        ? $this->subscriptionInterval
                        : 'month',
                    'interval_count' => max(1, $this->subscriptionIntervalCount),
                    'trial_days' => max(0, $this->subscriptionTrialDays),
                ];
            }
            if ($key === 'provisionable') {
                $providerSettings = $this->providerSettingsForPersistence();
                $config = [
                    'server_id' => $this->provisioningServerId,
                    'provider_key' => trim($this->providerKey) !== '' ? trim($this->providerKey) : null,
                    'provider_settings' => $providerSettings,
                ];
            }
            if ($key === 'domain_registration') {
                $domainName = strtolower(rtrim(trim($this->domainName), '.'));
                $config = [
                    'registrar_key' => trim($this->domainRegistrarKey) !== '' ? trim($this->domainRegistrarKey) : null,
                    'dns_provider_key' => trim($this->domainDnsProviderKey) !== '' ? trim($this->domainDnsProviderKey) : null,
                    'domain_name' => $domainName !== '' ? $domainName : null,
                    'auto_renew' => $this->domainAutoRenew,
                    'years' => max(1, min(10, $this->domainYears)),
                ];
            }
            if ($key === 'digital_secret') {
                $config = [
                    'source' => in_array($this->digitalSecretSource, ['pool', 'manual', 'provider'], true)
                        ? $this->digitalSecretSource
                        : 'pool',
                ];
            }
            if (! $this->product->hasCapability($key)) {
                $capabilities->enable($this->product, $key, $config);
                $this->product->unsetRelation('capabilities');
                $this->product->load('capabilities');
            } elseif (in_array($key, ['shippable', 'subscribable', 'provisionable', 'domain_registration', 'digital_secret'], true)) {
                $capabilities->syncConfig($this->product, $key, $config);
            }
        }

        foreach ($this->product->capabilities as $row) {
            if (! in_array($row->capability, $desired, true)) {
                $capabilities->disable($this->product, $row->capability);
            }
        }

        $this->product->refresh()->load('capabilities');
        $provisionable = $this->product->capability('provisionable');
        $publicSettings = $provisionable?->config['provider_settings'] ?? [];
        $this->providerSettings = is_array($publicSettings) ? $publicSettings : [];

        if (
            $this->product->hasCapability('inventory')
            && app()->bound(ProductStock::class)
        ) {
            app(ProductStock::class)
                ->setQuantity($this->product, max(0, $this->stockQuantity));
        }

        foreach ($this->product->capabilities as $row) {
            $this->capabilityEnabled[$row->capability] = true;
        }

        session()->flash('status', __('admin.products.flash.capabilities_updated'));
    }

    /** @return array<string, mixed> */
    private function providerSettingsForPersistence(): array
    {
        $settings = $this->providerSettings;
        $capability = $this->product->capability('provisionable');
        $existing = $capability?->runtimeConfig();
        $existingProvider = is_array($existing) ? ($existing['provider_key'] ?? null) : null;
        $sameProvider = is_string($existingProvider) && $existingProvider === trim($this->providerKey);
        $existingSettings = is_array($existing['provider_settings'] ?? null)
            ? $existing['provider_settings']
            : [];

        foreach ($this->providerSettingDefinitions() as $definition) {
            if (! $definition->secret || ! $sameProvider) {
                continue;
            }

            $submitted = $settings[$definition->key] ?? null;
            if (($submitted === null || $submitted === '' || $submitted === '[REDACTED]')
                && array_key_exists($definition->key, $existingSettings)
            ) {
                $settings[$definition->key] = $existingSettings[$definition->key];
            }
        }

        return $settings;
    }

    public function setDraft(): void
    {
        $this->authorize('products.update');

        $this->product->forceFill([
            'status' => ProductStatus::Draft,
        ])->save();

        $this->status = ProductStatus::Draft->value;
        session()->flash('status', __('admin.products.flash.set_draft'));
    }

    public function confirmDelete(): void
    {
        $this->authorize('products.delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteProduct(DeleteProduct $delete): void
    {
        $this->authorize('products.delete');

        try {
            $delete->handle($this->product);
            session()->flash('status', __('admin.products.flash.deleted'));
            $this->redirect(route('admin.products.index'), navigate: true);
        } catch (ValidationException $e) {
            $this->confirmingDelete = false;
            session()->flash('error', $e->errors()['product'][0] ?? $e->getMessage());
        }
    }

    private function validateDomainProviderSelections(): void
    {
        $errors = [];
        $registrarKey = trim($this->domainRegistrarKey);
        $dnsProviderKey = trim($this->domainDnsProviderKey);

        if ($registrarKey !== '') {
            $registrar = $this->domainProviderFromRegistry(
                'Agovena\\Modules\\Domains\\DomainRegistrarRegistry',
                $registrarKey,
            );
            if ($registrar === null) {
                $errors['domainRegistrarKey'] = __('admin.products.validation.domain_registrar_unavailable');
            } elseif (! method_exists($registrar, 'capabilities')
                || ! in_array('registration', $registrar->capabilities(), true)
            ) {
                $errors['domainRegistrarKey'] = __('admin.products.validation.domain_registrar_registration');
            }
        }

        if ($dnsProviderKey !== '') {
            $dnsProvider = $this->domainProviderFromRegistry(
                'Agovena\\Modules\\Domains\\DomainDnsProviderRegistry',
                $dnsProviderKey,
            );
            if ($dnsProvider === null) {
                $errors['domainDnsProviderKey'] = __('admin.products.validation.domain_dns_provider_unavailable');
            } elseif (! method_exists($dnsProvider, 'capabilities')
                || ! in_array('zone_management', $dnsProvider->capabilities(), true)
            ) {
                $errors['domainDnsProviderKey'] = __('admin.products.validation.domain_dns_provider_zone_management');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function domainProviderFromRegistry(string $registryClass, string $key): ?object
    {
        if (! class_exists($registryClass) || ! app()->bound($registryClass)) {
            return null;
        }

        $registry = app($registryClass);
        if (! method_exists($registry, 'get')) {
            return null;
        }

        $provider = $registry->get($key);

        return is_object($provider) ? $provider : null;
    }

    public function updatedProviderKey(): void
    {
        $defaults = [];
        foreach ($this->providerSettingDefinitions() as $definition) {
            $defaults[$definition->key] = $this->providerSettings[$definition->key] ?? $definition->default ?? '';
        }
        $this->providerSettings = $defaults;
    }

    public function updatedProvisioningServerId(?int $serverId): void
    {
        if ($serverId === null) {
            return;
        }
        $server = ProvisioningServer::query()->where('is_active', true)->find($serverId);
        if ($server !== null) {
            $this->providerKey = $server->provider_key;
            $this->updatedProviderKey();
        }
    }

    /**
     * @return list<ExtensionSettingDefinition>
     */
    private function providerSettingDefinitions(): array
    {
        if ($this->providerKey === '') {
            return [];
        }

        $provisioner = app(ProvisionerRegistry::class)->get($this->providerKey);
        if (! $provisioner instanceof ConfiguresProvisionedProducts) {
            return [];
        }

        return $provisioner->productSettings();
    }

    /** @return array<string, list<string>> */
    private function providerSettingRules(): array
    {
        $rules = [];
        foreach ($this->providerSettingDefinitions() as $definition) {
            $fieldRules = [$definition->required ? 'required' : 'nullable'];
            $fieldRules[] = $definition->type === 'boolean' ? 'boolean' : 'string';
            if ($definition->type !== 'boolean') {
                $fieldRules[] = 'max:10000';
            }
            $rules['providerSettings.'.$definition->key] = $fieldRules;
        }

        return $rules;
    }

    public function render(AdminRegistrar $admin, DeleteProduct $delete, ProductCapabilityRegistry $capabilities)
    {
        $provisioners = collect(app(ProvisionerRegistry::class)->all())
            ->map(static fn ($provisioner): array => [
                'id' => $provisioner->id(),
                'label' => $provisioner->label(),
            ])
            ->all();

        $productTabs = array_values(array_filter(
            $admin->productTabs(),
            static fn ($tab): bool => $tab->permission === null || auth()->user()?->can($tab->permission) === true,
        ));

        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'mode' => 'edit',
            'galleryImages' => $this->product->images,
            'isReferenced' => $delete->isReferencedByOrders($this->product),
            'availableCapabilities' => $capabilities->available(),
            'provisioners' => $provisioners,
            'canConfigureProvisioning' => $provisioners !== [],
            'providerSettingDefinitions' => $this->providerSettingDefinitions(),
            'domainRegistrars' => $this->domainProviderOptions('Agovena\\Modules\\Domains\\DomainRegistrarRegistry'),
            'domainDnsProviders' => $this->domainProviderOptions('Agovena\\Modules\\Domains\\DomainDnsProviderRegistry'),
            'provisioningServers' => Schema::hasTable('provisioning_servers')
                ? ProvisioningServer::query()->where('is_active', true)->orderBy('name')->get()
                : collect(),
            'productTabs' => $productTabs,
        ])->layout('layouts.admin', [
            'title' => __('admin.products.form.edit_title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /** @return list<array{key: string, capabilities: list<string>}> */
    private function domainProviderOptions(string $registryClass): array
    {
        if (! class_exists($registryClass) || ! app()->bound($registryClass)) {
            return [];
        }

        return collect(app($registryClass)->all())
            ->map(static fn (object $provider): array => [
                'key' => (string) $provider->key(),
                'capabilities' => array_values(array_map('strval', $provider->capabilities())),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function emptyCurrencyPriceInputs(string $nativeCurrency): array
    {
        $native = strtoupper($nativeCurrency);
        $inputs = [];
        foreach (Currency::query()->where('is_active', true)->orderBy('code')->pluck('code') as $code) {
            $code = strtoupper((string) $code);
            if ($code === $native) {
                continue;
            }
            $inputs[$code] = '';
        }

        return $inputs;
    }
}
