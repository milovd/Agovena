<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Catalog\UpdateProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class Edit extends Component
{
    use AuthorizesRequests;

    public Product $product;

    public string $name = '';

    public string $slug = '';

    public string $description = '';

    public string $status = 'draft';

    public string $price_amount = '0';

    public string $currency = 'EUR';

    public ?int $category_id = null;

    public function mount(Product $product): void
    {
        $this->authorize('products.update');

        $this->product = $product;
        $this->name = $product->name;
        $this->slug = $product->slug;
        $this->description = (string) $product->description;
        $this->status = $product->status->value;
        $this->price_amount = (string) $product->price_amount;
        $this->currency = $product->currency;
        $this->category_id = $product->category_id;
    }

    public function save(UpdateProduct $update): void
    {
        $this->authorize('products.update');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('products', 'slug')->ignore($this->product->id)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'price_amount' => ['required', 'integer', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $update->handle($this->product, [
            'name' => $data['name'],
            'slug' => $data['slug'] ?: null,
            'description' => $data['description'] ?: null,
            'status' => $data['status'],
            'price_amount' => (int) $data['price_amount'],
            'currency' => $data['currency'],
            'category_id' => $data['category_id'],
        ]);

        session()->flash('status', 'Product updated.');
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        return view('livewire.admin.products.form', [
            'categories' => Category::query()->orderBy('name')->get(),
            'mode' => 'edit',
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => 'Edit product',
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
