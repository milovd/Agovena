<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\CreateProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Currency;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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

    public function mount(): void
    {
        $this->authorize('products.create');

        $defaultCurrency = Currency::query()->where('is_active', true)->orderBy('code')->value('code');
        if (is_string($defaultCurrency) && $defaultCurrency !== '') {
            $this->currency = $defaultCurrency;
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

    public function save(CreateProduct $create): void
    {
        $this->authorize('products.create');

        $currencyRule = Currency::query()->where('is_active', true)->exists()
            ? Rule::exists('currencies', 'code')->where('is_active', true)
            : ['string', 'size:3'];

        $data = $this->validate([
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
        ]);

        try {
            $priceAmount = MoneyFormatter::minorFromMajorInput($data['price'], $data['currency']);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'price' => $e->getMessage(),
            ]);
        }

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

        session()->flash('status', __('admin.products.flash.created'));

        $this->redirect(route('admin.products.edit', $product), navigate: true);
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(['code', 'name']),
            'mode' => 'create',
            'galleryImages' => collect(),
            'availableCapabilities' => [],
        ])->layout('layouts.admin', [
            'title' => __('admin.products.form.create_title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
