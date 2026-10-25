<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Orders\DeleteOrder;
use App\Agovena\Orders\DeleteSelectedOrders;
use App\Agovena\Orders\OrderListStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;
    use WithPagination;

    public string $search = '';

    /** One combined list status (OrderListStatus value), or '' for every order. */
    public string $status = '';

    public ?int $confirmingDeleteId = null;

    /** @var array<int, mixed> */
    public array $selectedOrderIds = [];

    /** @var array{deleted: int, kept: int, skipped: int, failed: int}|null */
    #[Locked]
    public ?array $bulkDeleteResult = null;

    public function mount(): void
    {
        $this->authorize('orders.view');
    }

    public function updatingSearch(): void
    {
        $this->selectedOrderIds = [];
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->selectedOrderIds = [];
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        if (OrderListStatus::tryFrom($this->status) === null) {
            $this->status = '';
        }
    }

    public function updatedPaginators(): void
    {
        $this->selectedOrderIds = [];
    }

    public function selectCurrentPage(): void
    {
        $this->authorize('orders.delete');
        $visibleIds = $this->visibleOrders()->getCollection()->pluck('id')->all();
        $selectedIds = array_map(static fn (mixed $id): string => is_int($id) || is_string($id) ? (string) $id : '', $this->selectedOrderIds);
        $allSelected = $visibleIds !== [] && collect($visibleIds)->every(static fn (int $id): bool => in_array((string) $id, $selectedIds, true));
        $this->selectedOrderIds = $allSelected ? [] : $visibleIds;
    }

    public function bulkDeleteOrders(DeleteSelectedOrders $deleteSelected): void
    {
        $this->authorize('orders.delete');
        [$requested, $ids] = $this->selectionOnCurrentPage();

        // The selection stays in place while the password modal is open, so the confirmed
        // retry deletes exactly what the merchant selected.
        if (! $this->requireRecentPassword('bulkDeleteOrders')) {
            return;
        }

        $staff = Auth::user();
        if (! $staff instanceof User) {
            abort(403);
        }

        $result = $deleteSelected->handle($ids, $staff);
        $result['skipped'] += count($requested) - count($ids);
        $this->selectedOrderIds = [];
        $this->bulkDeleteResult = $result;
        if ($result['deleted'] > 0) {
            $this->resetPage();
        }
    }

    /**
     * The requested ids (deduplicated strings) and the ones that are really on the current,
     * filtered page; anything else in the browser state is ignored and counted as skipped.
     *
     * @return array{0: list<string>, 1: list<int>}
     */
    private function selectionOnCurrentPage(): array
    {
        // Check the raw count before deduplicating hostile Livewire state.
        if (count($this->selectedOrderIds) > DeleteSelectedOrders::MAX_SELECTION) {
            throw ValidationException::withMessages(['selectedOrderIds' => __('admin.orders.bulk_limit')]);
        }
        $requested = array_values(array_unique(array_map(
            static fn (mixed $id): string => is_int($id) || is_string($id) ? (string) $id : '',
            $this->selectedOrderIds,
        )));
        if ($requested === [] || $requested === ['']) {
            throw ValidationException::withMessages(['selectedOrderIds' => __('admin.orders.bulk_empty')]);
        }
        $visibleIds = $this->visibleOrders()->getCollection()->pluck('id')->all();
        $ids = array_values(array_filter($visibleIds, static fn (int $id): bool => in_array((string) $id, $requested, true)));

        return [$requested, $ids];
    }

    public function confirmDelete(int $orderId): void
    {
        $this->authorize('orders.delete');
        $this->confirmingDeleteId = Order::query()->whereKey($orderId)->exists() ? $orderId : null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteOrder(DeleteOrder $delete): void
    {
        $this->authorize('orders.delete');

        if ($this->confirmingDeleteId === null || ! $this->requireRecentPassword('deleteOrder')) {
            return;
        }

        $staff = Auth::user();
        if (! $staff instanceof User) {
            abort(403);
        }

        $order = Order::query()->find($this->confirmingDeleteId);
        if ($order === null) {
            $this->confirmingDeleteId = null;

            return;
        }

        try {
            $delete->handle($order, $staff);
            $this->confirmingDeleteId = null;
            $this->resetPage();
            session()->flash('status', __('admin.orders.flash.deleted'));
        } catch (ValidationException $exception) {
            $this->confirmingDeleteId = null;
            session()->flash('error', $exception->errors()['order'][0] ?? $exception->getMessage());
        }
    }

    public function render(AdminRegistrar $admin)
    {
        $this->authorize('orders.view');
        $orders = $this->visibleOrders();
        $confirmingOrder = $this->confirmingDeleteId === null
            ? null
            : Order::query()->find($this->confirmingDeleteId);

        return view('livewire.admin.orders.index', [
            'orders' => $orders,
            'listStatuses' => OrderListStatus::cases(),
            'confirmingOrder' => $confirmingOrder,
        ])->layout('layouts.admin', [
            'title' => __('admin.orders.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    /** @return LengthAwarePaginator<int, Order> */
    private function visibleOrders(): LengthAwarePaginator
    {
        $query = Order::query()->with('payment');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term);
            });
        }

        $status = OrderListStatus::tryFrom($this->status);
        if ($status !== null) {
            OrderListStatus::apply($query, $status);
        }

        return $query->orderByDesc('id')->paginate(15);
    }
}
