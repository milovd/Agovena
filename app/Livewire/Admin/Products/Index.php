<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\DeleteProduct;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $category = '';

    public string $sort = 'newest';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('products.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCategory(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function setStatus(int $productId, string $status): void
    {
        $this->authorize('products.update');

        $product = Product::query()->findOrFail($productId);
        $product->forceFill([
            'status' => ProductStatus::from($status),
        ])->save();

        session()->flash('status', __('admin.products.flash.status_updated'));
    }

    public function confirmDelete(int $productId): void
    {
        $this->authorize('products.delete');
        $this->confirmingDeleteId = $productId;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function draftAndClose(int $productId): void
    {
        $this->setStatus($productId, 'draft');
        $this->cancelDelete();
    }

    public function deleteProduct(DeleteProduct $delete): void
    {
        $this->authorize('products.delete');

        if ($this->confirmingDeleteId === null) {
            return;
        }

        $product = Product::query()->findOrFail($this->confirmingDeleteId);

        try {
            $delete->handle($product);
            session()->flash('status', __('admin.products.flash.deleted'));
        } catch (ValidationException $e) {
            $message = $e->errors()['product'][0] ?? $e->getMessage();
            session()->flash('error', $message);
        }

        $this->confirmingDeleteId = null;
    }

    public function render(AdminRegistrar $admin, DeleteProduct $delete)
    {
        $query = Product::query()->with('category');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('slug', 'like', $term)
                    ->orWhere('sku', 'like', $term);
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->category !== '') {
            $query->where('category_id', (int) $this->category);
        }

        match ($this->sort) {
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderBy('price_amount')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price_amount')->orderBy('name'),
            'updated' => $query->orderByDesc('updated_at'),
            default => $query->orderByDesc('id'),
        };

        $products = $query->paginate(15);

        $confirming = $this->confirmingDeleteId
            ? Product::query()->find($this->confirmingDeleteId)
            : null;

        return view('livewire.admin.products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'confirmingProduct' => $confirming,
            'confirmingReferenced' => $confirming ? $delete->isReferencedByOrders($confirming) : false,
        ])->layout('layouts.admin', [
            'title' => __('admin.products.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
