<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Orders\DeleteOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $paymentStatus = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('orders.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingPaymentStatus(): void
    {
        $this->resetPage();
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
        $query = Order::query()->with('payment');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term);
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        if ($this->paymentStatus !== '') {
            $query->whereHas('payment', fn ($q) => $q->where('status', $this->paymentStatus));
        }

        $orders = $query->orderByDesc('id')->paginate(15);
        $confirmingOrder = $this->confirmingDeleteId === null
            ? null
            : Order::query()->find($this->confirmingDeleteId);

        return view('livewire.admin.orders.index', [
            'orders' => $orders,
            'orderStatuses' => OrderStatus::cases(),
            'paymentStatuses' => PaymentStatus::cases(),
            'confirmingOrder' => $confirmingOrder,
        ])->layout('layouts.admin', [
            'title' => __('admin.orders.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
