<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class InvoiceShow extends Component
{
    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $customer = Auth::guard('customer')->user();

        abort_unless(
            $customer !== null && (int) $invoice->customer_id === (int) $customer->id,
            404,
        );

        $this->invoice = $invoice->load('items');
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.invoices.show'), [
            'theme' => $theme,
            'invoice' => $this->invoice,
            'accountSection' => 'invoices',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.invoice_title', ['number' => $this->invoice->number]),
            'theme' => $theme,
        ]);
    }
}
