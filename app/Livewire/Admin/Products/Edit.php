<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\Capabilities\ProductCapabilityDefinition;
use App\Agovena\Catalog\Capabilities\ProductCapabilityManager;
use App\Agovena\Catalog\Capabilities\ProductCapabilityRegistry;
use App\Agovena\Catalog\Contracts\ProductStock;
use App\Agovena\Catalog\DeleteProduct;
use App\Agovena\Catalog\UpdateProduct;
use App\Agovena\Extensions\ExtensionSettingDefinition;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisionedProducts;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
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

    /** @var array<string, mixed> */
    public array $providerSettings = [];

    public function mount(Product $product): void
    {
        $this->authorize('products.update');

        $this->product = $product->load(['images', 'capabilities']);
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
                $settings = $row->config['provider_settings'] ?? [];
                $this->providerSettings = is_array($settings) ? $settings : [];
            }
        }

        if (app()->bound(ProductStock::class)) {
            $this->stockQuantity = app(ProductStock::class)
                ->quantityFor($product);
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

        foreach (array_keys($this->capabilityEnabled) as $key) {
            $this->capabilityEnabled[$key] = false;
        }
        foreach ($available as $key) {
            $this->capabilityEnabled[$key] = false;
        }

        $requested = match ($preset) {
            'physical' => ['physical', 'inventory', 'shippable'],
            'digital' => ['digital'],
            'subscription' => ['subscribable'],
            'hosted_service' => $this->hostedServiceSubscription
                ? ['provisionable', 'subscribable']
                : ['provisionable'],
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
            'subscription',
            'hosted_service',
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

    public function save(UpdateProduct $update): void
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
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        try {
            $priceAmount = MoneyFormatter::minorFromMajorInput($data['price'], $data['currency']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => $e->getMessage(),
            ]);
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

        $this->product->refresh()->load('images');
        session()->flash('status', __('admin.products.flash.updated'));
    }

    public function saveCapabilities(ProductCapabilityManager $capabilities, ProductCapabilityRegistry $registry): void
    {
        $this->authorize('products.update');

        $this->product->load('capabilities');

        $desired = array_keys(array_filter($this->capabilityEnabled));
        $available = collect($registry->available())->keyBy(static fn ($d) => $d->key);

        $desired = array_values(array_filter(
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
                $config = [
                    'provider_key' => trim($this->providerKey) !== '' ? trim($this->providerKey) : null,
                    'provider_settings' => $this->providerSettings,
                ];
            }
            if (! $this->product->hasCapability($key)) {
                $capabilities->enable($this->product, $key, $config);
                $this->product->unsetRelation('capabilities');
                $this->product->load('capabilities');
            } elseif (in_array($key, ['shippable', 'subscribable', 'provisionable'], true)) {
                $capabilities->syncConfig($this->product, $key, $config);
            }
        }

        foreach ($this->product->capabilities as $row) {
            if (! in_array($row->capability, $desired, true)) {
                $capabilities->disable($this->product, $row->capability);
            }
        }

        $this->product->refresh()->load('capabilities');

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

    public function updatedProviderKey(): void
    {
        $defaults = [];
        foreach ($this->providerSettingDefinitions() as $definition) {
            $defaults[$definition->key] = $this->providerSettings[$definition->key] ?? $definition->default ?? '';
        }
        $this->providerSettings = $defaults;
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

    public function render(AdminRegistrar $admin, DeleteProduct $delete, ProductCapabilityRegistry $capabilities)
    {
        $provisioners = collect(app(ProvisionerRegistry::class)->all())
            ->map(static fn ($provisioner): array => [
                'id' => $provisioner->id(),
                'label' => $provisioner->label(),
            ])
            ->all();

        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'mode' => 'edit',
            'galleryImages' => $this->product->images,
            'isReferenced' => $delete->isReferencedByOrders($this->product),
            'availableCapabilities' => $capabilities->available(),
            'provisioners' => $provisioners,
            'providerSettingDefinitions' => $this->providerSettingDefinitions(),
        ])->layout('layouts.admin', [
            'title' => __('admin.products.form.edit_title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
