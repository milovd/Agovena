<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\CreditNote;
use App\Models\Invoice;
use Livewire\Component;
use Livewire\WithPagination;

final class InvoicesIndex extends Component
{
    use WithPagination;

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $customer = authenticated_customer();

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->paginate(10, ['*'], 'invoices');

        $creditNotes = CreditNote::query()
            ->where('customer_id', $customer->id)
            ->latest('id')
            ->paginate(10, ['*'], 'credit_notes');

        return view($theme->view('account.invoices.index'), [
            'theme' => $theme,
            'invoices' => $invoices,
            'creditNotes' => $creditNotes,
            'accountSection' => 'invoices',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.invoices_title'),
            'theme' => $theme,
        ]);
    }
}
