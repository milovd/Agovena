<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordManualPayment;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Order $order;

    public string $reference = '';

    public bool $confirmingPayment = false;

    public bool $confirmingCancel = false;

    public function mount(Order $order): void
    {
        $this->authorize('orders.view');
        $this->order = $order->load(['items', 'payment.attempts', 'invoice', 'creditNotes', 'refunds']);
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

    public function startCancelUnpaid(): void
    {
        $this->authorizeCancelUnpaid();
        $this->confirmingCancel = true;
    }

    public function abortCancelUnpaid(): void
    {
        $this->confirmingCancel = false;
    }

    public function cancelUnpaid(CancelUnpaidOrder $cancel): void
    {
        $this->authorizeCancelUnpaid();

        if (! $this->requireRecentPassword('cancelUnpaid')) {
            return;
        }

        /** @var User $staff */
        $staff = Auth::user();

        $this->order = $cancel->handle($this->order, UnpaidOrderCancelSource::Staff, $staff)
            ->load(['items', 'payment.attempts', 'invoice', 'creditNotes', 'refunds']);
        $this->confirmingCancel = false;
        session()->flash('status', __('admin.orders.flash.cancelled'));
    }

    public function recordPayment(RecordManualPayment $action): void
    {
        $this->authorize('payments.record');

        if (! $this->requireRecentPassword('recordPayment')) {
            return;
        }

        /** @var User $staff */
        $staff = Auth::user();

        $action->handle(
            $this->order,
            $staff,
            filled($this->reference) ? $this->reference : null,
        );

        $this->order->refresh()->load(['items', 'payment.attempts', 'invoice', 'creditNotes', 'refunds']);
        $this->confirmingPayment = false;
        session()->flash('status', __('admin.orders.flash.payment_recorded'));
    }

    public function render(AdminRegistrar $admin)
    {
        /** @var InMemoryAdminRegistrar $admin */
        $user = Auth::user();
        $canRecord = $user?->can('payments.record') === true
            && $this->order->payment?->status === PaymentStatus::Pending;
        $canCancelUnpaid = $this->order->canCancelUnpaid()
            && ($user?->can('orders.cancel') === true || $user?->can('invoices.void') === true);

        return view('livewire.admin.orders.show', [
            'canRecord' => $canRecord,
            'canCancelUnpaid' => $canCancelUnpaid,
            'orderDetailSections' => $admin->orderDetailSections(),
            'navigation' => $admin->navigationItems(),
            'paymentGatewayLabel' => $this->paymentGatewayLabel(),
        ])->layout('layouts.admin', [
            'title' => __('admin.orders.show.title', ['number' => $this->order->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function paymentGatewayLabel(): string
    {
        $method = $this->order->payment?->method;
        if (! is_string($method) || $method === '') {
            return '-';
        }

        $gateway = app(PaymentGatewayRegistry::class)->get($method);
        if ($gateway !== null) {
            return __($gateway->label());
        }

        $key = 'admin.orders.method.'.$method;

        return Lang::has($key) ? __($key) : $method;
    }

    private function authorizeCancelUnpaid(): void
    {
        $user = Auth::user();
        abort_unless(
            $user instanceof User
                && ($user->can('orders.cancel') || $user->can('invoices.void')),
            403,
        );
    }
}
