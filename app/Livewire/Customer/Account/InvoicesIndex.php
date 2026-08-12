<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

final class InvoicesIndex extends Component
{
    use WithPagination;

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $customer = Auth::guard('customer')->user();

        $invoices = Invoice::query()
            ->where('customer_id', $customer?->id)
            ->latest('id')
            ->paginate(10);

        return view($theme->view('account.invoices.index'), [
            'theme' => $theme,
            'invoices' => $invoices,
            'accountSection' => 'invoices',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.invoices_title'),
            'theme' => $theme,
        ]);
    }
}
