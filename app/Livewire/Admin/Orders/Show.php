<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Payments\RecordManualPayment;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\StaffUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;

    public Order $order;

    public string $reference = '';

    public bool $confirmingPayment = false;

    public function mount(Order $order): void
    {
        $this->authorize('orders.view');
        $this->order = $order->load(['items', 'payment']);
    }

    public function startRecordPayment(): void
    {
        $this->authorize('payments.record');
        $this->confirmingPayment = true;
    }

    public function cancelRecordPayment(): void
    {
        $this->confirmingPayment = false;
    }

    public function recordPayment(RecordManualPayment $action): void
    {
        $this->authorize('payments.record');

        /** @var StaffUser $staff */
        $staff = Auth::guard('staff')->user();

        $action->handle(
            $this->order,
            $staff,
            filled($this->reference) ? $this->reference : null,
        );

        $this->order->refresh()->load(['items', 'payment']);
        $this->confirmingPayment = false;
        session()->flash('status', __('admin.orders.flash.payment_recorded'));
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        $canRecord = Auth::guard('staff')->user()?->can('payments.record') === true
            && $this->order->payment?->status === PaymentStatus::Pending;

        return view('livewire.admin.orders.show', [
            'canRecord' => $canRecord,
            'orderDetailSections' => $admin->orderDetailSections(),
            'navigation' => $admin->navigationItems(),
        ])->layout('layouts.admin', [
            'title' => __('admin.orders.show.title', ['number' => $this->order->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
