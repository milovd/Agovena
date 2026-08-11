<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Catalog\CreateProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Create extends Component
{
    use AuthorizesRequests;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $status = 'draft';

    public string $price_amount = '0';

    public string $currency = 'EUR';

    public ?int $category_id = null;

    public function mount(): void
    {
        $this->authorize('products.create');
    }

    public function save(CreateProduct $create): void
    {
        $this->authorize('products.create');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:products,slug'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $product = $create->handle([
            'name' => $data['name'],
            'slug' => $data['slug'] ?: null,
            'description' => $data['description'] ?: null,
            'status' => $data['status'],
            'price_amount' => (int) $data['price_amount'],
            'currency' => $data['currency'],
            'category_id' => $data['category_id'],
        ]);

        session()->flash('status', 'Product created.');

        $this->redirect(route('admin.products.edit', $product), navigate: true);
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'mode' => 'create',
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => 'Create product',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
