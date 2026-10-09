<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Catalog\DeleteProduct;
use App\Agovena\Catalog\SetProductStatus;
use App\Agovena\Catalog\SetSelectedProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
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

    /** @var array<int, mixed> */
    public array $selectedProductIds = [];

    /** @var array{updated: int, unchanged: int, skipped: int, failed: int}|null */
    #[Locked]
    public ?array $bulkResult = null;

    private const MAX_BULK_SELECTION = 500;

    public function mount(): void
    {
        $this->authorize('products.view');
    }

    public function updatedSearch(): void
    {
        $this->selectedProductIds = [];
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->selectedProductIds = [];
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->selectedProductIds = [];
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->selectedProductIds = [];
        $this->resetPage();
    }

    public function updatedPaginators(): void
    {
        $this->selectedProductIds = [];
    }

    public function selectCurrentPage(): void
    {
        $this->authorize('products.update');
        $this->selectedProductIds = $this->visibleProducts()->getCollection()->pluck('id')->all();
    }

    public function bulkSetStatus(string $status): void
    {
        $this->authorize('products.update');
        if (! in_array($status, ['draft', 'active'], true)) {
            throw ValidationException::withMessages(['bulkStatus' => __('admin.products.bulk_invalid_status')]);
        }
        // Check the raw count before deduplicating hostile Livewire state.
        if (count($this->selectedProductIds) > self::MAX_BULK_SELECTION) {
            throw ValidationException::withMessages(['selectedProductIds' => __('admin.products.bulk_limit')]);
        }
        $requested = array_unique(array_map(
            static fn (mixed $id): string => is_int($id) || is_string($id) ? (string) $id : '',
            $this->selectedProductIds,
        ));
        if ($requested === [] || $requested === ['']) {
            throw ValidationException::withMessages(['selectedProductIds' => __('admin.products.bulk_empty')]);
        }
        $visibleIds = $this->visibleProducts()->getCollection()->pluck('id')->all();
        $ids = array_values(array_filter($visibleIds, static fn (int $id): bool => in_array((string) $id, $requested, true)));
        $result = app(SetSelectedProductStatus::class)->handle($ids, $status);
        $result['skipped'] += count($requested) - count($ids);
        $this->selectedProductIds = [];
        $this->bulkResult = $result;
    }

    public function setStatus(int $productId, string $status): void
    {
        $this->authorize('products.update');

        $product = Product::query()->findOrFail($productId);
        app(SetProductStatus::class)->handle($product, $status);

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
        $this->authorize('products.view');
        $products = $this->visibleProducts();
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

    /** @return LengthAwarePaginator<int, Product> */
    private function visibleProducts(): LengthAwarePaginator
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

        return $query->paginate(15);
    }
}
