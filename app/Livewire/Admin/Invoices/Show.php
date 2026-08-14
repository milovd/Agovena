<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Invoices;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Invoices\VoidInvoice;
use App\Agovena\Payments\RecordRefund;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Invoice;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Invoice $invoice;

    public string $refundAmount = '';

    public string $refundReason = '';

    public string $refundCreditNoteId = '';

    public bool $confirmingVoid = false;

    public bool $confirmingRefund = false;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('invoices.view');
        $this->refreshInvoice($invoice);
    }

    public function startVoid(): void
    {
        $this->authorize('invoices.void');
        $this->confirmingVoid = true;
    }

    public function cancelVoid(): void
    {
        $this->confirmingVoid = false;
    }

    public function voidInvoice(VoidInvoice $action): void
    {
        $this->authorize('invoices.void');

        if (! $this->requireRecentPassword('voidInvoice')) {
            return;
        }

        /** @var User $staff */
        $staff = Auth::user();
        $action->handle($this->invoice, $staff);
        $this->refreshInvoice($this->invoice);
        $this->confirmingVoid = false;
        session()->flash('status', __('admin.invoices.voided'));
    }

    public function startRefund(): void
    {
        $this->authorize('payments.refund');
        $this->confirmingRefund = true;
    }

    public function cancelRefund(): void
    {
        $this->confirmingRefund = false;
    }

    public function recordRefund(RecordRefund $action): void
    {
        $this->authorize('payments.refund');

        if (! $this->requireRecentPassword('recordRefund')) {
            return;
        }

        $payment = $this->invoice->order?->payment;
        abort_unless($payment !== null, 404);

        try {
            $amount = MoneyFormatter::minorFromMajorInput($this->refundAmount, $payment->currency);
        } catch (InvalidArgumentException $e) {
            $this->addError('refundAmount', $e->getMessage());

            return;
        }

        /** @var User $staff */
        $staff = Auth::user();
        $creditNoteId = $this->refundCreditNoteId !== '' ? (int) $this->refundCreditNoteId : null;

        $action->handle(
            $payment,
            $staff,
            $amount,
            $this->refundReason,
            $creditNoteId,
        );

        $this->refreshInvoice($this->invoice);
        $this->confirmingRefund = false;
        $this->refundAmount = '';
        $this->refundReason = '';
        $this->refundCreditNoteId = '';
        session()->flash('status', __('admin.refunds.recorded'));
    }

    public function render(AdminRegistrar $admin)
    {
        $user = Auth::user();
        $payment = $this->invoice->order?->payment;

        return view('livewire.admin.invoices.show', [
            'invoice' => $this->invoice,
            'canVoid' => $user?->can('invoices.void') === true && $this->invoice->canVoid(),
            'canCredit' => $user?->can('invoices.credit') === true && $this->invoice->canIssueCreditNote(),
            'canRefund' => $user?->can('payments.refund') === true
                && $payment !== null
                && $payment->remainingRefundable() > 0,
            'payment' => $payment,
        ])->layout('layouts.admin', [
            'title' => __('admin.invoices.show_title', ['number' => $this->invoice->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function refreshInvoice(Invoice $invoice): void
    {
        $this->invoice = $invoice->fresh(['items', 'order.payment', 'creditNotes', 'refunds']) ?? $invoice;
    }
}
