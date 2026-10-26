<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Admin\InMemoryAdminRegistrar;
use App\Agovena\Admin\OrderDetailSection;
use App\Agovena\Invoices\LinkInvoiceToOrder;
use App\Agovena\Invoices\UnlinkInvoiceFromOrder;
use App\Agovena\Orders\CancelUnpaidOrder;
use App\Agovena\Orders\ChangeOrderCustomer;
use App\Agovena\Orders\DeleteOrder;
use App\Agovena\Orders\UnpaidOrderCancelSource;
use App\Agovena\Orders\UnpaidOrderEditGuard;
use App\Agovena\Orders\UpdateOrder;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Recurring\Models\Subscription;
use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Livewire\Forms\Admin\OrderDetailsForm;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Order $order;

    public string $reference = '';

    public bool $confirmingPayment = false;

    public bool $confirmingCancel = false;

    public bool $showInvoiceLinker = false;

    public string $invoiceSearch = '';

    public OrderDetailsForm $details;

    public bool $editingDetails = false;

    public bool $editingAddresses = false;

    public bool $confirmingDelete = false;

    public string $customerSearch = '';

    public ?int $selectedCustomerId = null;

    public function mount(Order $order): void
    {
        $this->authorize('orders.view');
        $this->order = $order->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds']);
        $this->details->fillFrom($this->order);
    }

    public function startEditDetails(): void
    {
        $this->authorize('orders.update');
        $this->details->fillFrom($this->order);
        $this->selectedCustomerId = $this->order->customer_id === null ? null : (int) $this->order->customer_id;
        $this->customerSearch = $this->customerLabel($this->order->customer);
        $this->editingAddresses = false;
        $this->editingDetails = true;
    }

    public function cancelEditDetails(): void
    {
        $this->editingDetails = false;
        $this->customerSearch = '';
        $this->details->fillFrom($this->order);
    }

    public function updatedCustomerSearch(): void
    {
        $this->authorize('orders.update');
        // Typing starts a new search; the choice only counts once a result is picked.
        $this->selectedCustomerId = null;
    }

    public function selectCustomer(int $customerId): void
    {
        $this->authorize('orders.update');
        $customer = Customer::query()->findOrFail($customerId);
        $this->selectedCustomerId = (int) $customer->id;
        $this->customerSearch = $this->customerLabel($customer);
        $this->details->customerName = (string) $customer->name;
        $this->details->customerEmail = (string) $customer->email;
        $this->resetValidation('order');
    }

    public function saveDetails(UpdateOrder $update, ChangeOrderCustomer $changeCustomer): void
    {
        $this->authorize('orders.update');
        $attributes = $this->details->detailsAttributes();
        $staff = $this->staff();

        $customerChanges = $this->selectedCustomerId !== null
            && $this->selectedCustomerId !== (int) $this->order->customer_id;

        try {
            if ($customerChanges) {
                $customer = Customer::query()->findOrFail($this->selectedCustomerId);
                app(UnpaidOrderEditGuard::class)->assertEditable($this->order, forCustomerChange: true);
                if (! $this->requireRecentPassword('saveDetails')) {
                    return;
                }
                $changeCustomer->handle($this->order, $customer, $staff);
            }

            $update->handle($this->order->fresh() ?? $this->order, $attributes);
        } catch (ValidationException $exception) {
            $this->addError('order', $exception->errors()['order'][0] ?? $exception->getMessage());

            return;
        }

        $this->refreshOrder();
        $this->details->fillFrom($this->order);
        $this->editingDetails = false;
        $this->customerSearch = '';
        session()->flash('status', __($customerChanges ? 'admin.orders.flash.customer_changed' : 'admin.orders.flash.updated'));
    }

    public function startEditAddresses(): void
    {
        $this->authorize('orders.update');
        $this->details->fillFrom($this->order);
        $this->editingDetails = false;
        $this->editingAddresses = true;
    }

    public function cancelEditAddresses(): void
    {
        $this->editingAddresses = false;
        $this->details->fillFrom($this->order);
    }

    public function saveAddresses(UpdateOrder $update): void
    {
        $this->authorize('orders.update');
        $this->order = $update->handle($this->order, $this->details->addressAttributes());
        $this->refreshOrder();
        $this->details->fillFrom($this->order);
        $this->editingAddresses = false;
        session()->flash('status', __('admin.orders.flash.updated'));
    }

    public function confirmDelete(): void
    {
        $this->authorize('orders.delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteOrder(DeleteOrder $delete): void
    {
        $this->authorize('orders.delete');
        if (! $this->requireRecentPassword('deleteOrder')) {
            return;
        }

        try {
            $delete->handle($this->order, $this->staff());
        } catch (ValidationException $exception) {
            $this->confirmingDelete = false;
            session()->flash('error', $exception->errors()['order'][0] ?? $exception->getMessage());

            return;
        }

        session()->flash('status', __('admin.orders.flash.deleted'));
        $this->redirect(route('admin.orders.index'), navigate: true);
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
            ->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds']);
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

        $this->order->refresh()->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds']);
        $this->confirmingPayment = false;
        session()->flash('status', __('admin.orders.flash.payment_recorded'));
    }

    public function startLinkInvoice(): void
    {
        $this->authorize('invoices.manage');
        $this->showInvoiceLinker = true;
    }

    public function cancelLinkInvoice(): void
    {
        $this->showInvoiceLinker = false;
        $this->invoiceSearch = '';
    }

    public function linkInvoice(int $invoiceId, LinkInvoiceToOrder $link): void
    {
        $this->authorize('invoices.manage');

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        /** @var User $staff */
        $staff = Auth::user();
        $link->handle($invoice, $this->order, $staff);
        $this->refreshOrder();
        $this->cancelLinkInvoice();
        session()->flash('status', __('admin.orders.flash.invoice_linked'));
    }

    public function unlinkInvoice(int $invoiceId, UnlinkInvoiceFromOrder $unlink): void
    {
        $this->authorize('invoices.manage');

        $invoice = Invoice::query()->whereKey($invoiceId)->firstOrFail();
        abort_unless((int) $invoice->order_id === (int) $this->order->id, 404);
        /** @var User $staff */
        $staff = Auth::user();
        $unlink->handle($invoice, $staff);
        $this->refreshOrder();
        session()->flash('status', __('admin.orders.flash.invoice_unlinked'));
    }

    public function render(AdminRegistrar $admin)
    {
        $this->authorize('orders.view');
        /** @var InMemoryAdminRegistrar $admin */
        $user = Auth::user();
        $canRecord = $user?->can('payments.record') === true
            && $this->order->payment?->status === PaymentStatus::Pending;
        $canCancelUnpaid = $this->order->canCancelUnpaid()
            && ($user?->can('orders.cancel') === true || $user?->can('invoices.void') === true);
        $canManageInvoices = $user?->can('invoices.manage') === true
            && $this->order->status === OrderStatus::Pending
            && ($this->order->payment === null || $this->order->payment->status === PaymentStatus::Pending)
            && ! $this->order->invoices()->where(function ($query): void {
                $query->where('status', '!=', InvoiceStatus::Issued)
                    ->orWhereNotNull('paid_at')
                    ->orWhereHas('creditNotes')
                    ->orWhereHas('refunds');
            })->exists();
        $invoiceCandidates = collect();
        if ($this->showInvoiceLinker && $canManageInvoices) {
            $term = trim($this->invoiceSearch);
            $invoiceCandidates = Invoice::query()
                ->whereNull('order_id')
                ->where('status', InvoiceStatus::Issued)
                ->when($term !== '', static function ($query) use ($term): void {
                    $query->where(function ($query) use ($term): void {
                        $query->where('number', 'like', "%{$term}%")
                            ->orWhere('customer_name', 'like', "%{$term}%")
                            ->orWhere('customer_email', 'like', "%{$term}%");
                    });
                })
                ->latest('id')
                ->limit(8)
                ->get();
        }

        return view('livewire.admin.orders.show', [
            'canRecord' => $canRecord,
            'canUpdate' => $user?->can('orders.update') === true,
            'canDelete' => $user?->can('orders.delete') === true,
            'customerLockReason' => app(UnpaidOrderEditGuard::class)->blockingReason($this->order, forCustomerChange: true),
            'customerResults' => $this->editingDetails ? $this->customerResults() : collect(),
            'subscriptionsByItem' => Subscription::query()
                ->where('order_id', $this->order->id)
                ->whereNotNull('order_item_id')
                ->get()
                ->keyBy('order_item_id'),
            'canViewSubscriptions' => $user?->can('subscriptions.view') === true,
            'displayableProperties' => array_values(array_filter(
                $this->order->custom_properties_snapshot ?? [],
                static fn (mixed $property): bool => is_array($property)
                    && is_scalar($property['label'] ?? $property['key'] ?? null)
                    && is_scalar($property['value'] ?? null),
            )),
            'canCancelUnpaid' => $canCancelUnpaid,
            'canManageInvoices' => $canManageInvoices,
            'invoiceCandidates' => $invoiceCandidates,
            'orderDetailSections' => array_values(array_filter(
                $admin->orderDetailSections(),
                fn (OrderDetailSection $section): bool => $section->permission === null
                    || (auth()->user()?->can($section->permission) ?? false),
            )),
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

    private function refreshOrder(): void
    {
        $this->order = Order::query()->whereKey($this->order->id)->firstOrFail()
            ->load(['items', 'payment.attempts', 'invoices', 'creditNotes', 'refunds']);
    }

    /** @return Collection<int, Customer> */
    private function customerResults(): Collection
    {
        $term = trim($this->customerSearch);
        if ($term === '' || $this->selectedCustomerId !== null) {
            return collect();
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return Customer::query()
            ->where(static function ($query) use ($like): void {
                $query->where('name', 'like', $like)->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);
    }

    private function customerLabel(?Customer $customer): string
    {
        return $customer === null ? '' : $customer->name.' ('.$customer->email.')';
    }

    private function staff(): User
    {
        $staff = Auth::user();
        abort_unless($staff instanceof User, 403);

        return $staff;
    }
}
