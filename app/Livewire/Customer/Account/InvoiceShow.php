<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\Invoice;
use App\Models\Order;
use Livewire\Component;

final class InvoiceShow extends Component
{
    use PaysUnpaidOrders;

    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $customer = authenticated_customer();

        abort_unless(
            (int) $invoice->customer_id === (int) $customer->id,
            404,
        );

        $this->invoice = $invoice->load(['items', 'order.payment', 'creditNotes']);
    }

    protected function unpaidOrder(): ?Order
    {
        $order = $this->invoice->order;

        return $order !== null && $order->isRetryablePayment() ? $order : null;
    }

    protected function afterPaymentAttempt(Order $order): void
    {
        $this->invoice = $this->invoice->fresh(['items', 'order.payment', 'creditNotes']) ?? $this->invoice;
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.invoices.show'), [
            'theme' => $theme,
            'invoice' => $this->invoice,
            'accountSection' => 'invoices',
            'paymentOptions' => $this->paymentGatewayOptions(),
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.invoice_title', ['number' => $this->invoice->number]),
            'theme' => $theme,
        ]);
    }
}
