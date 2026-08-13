<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\CreditNote;
use Livewire\Component;

final class CreditNoteShow extends Component
{
    public CreditNote $creditNote;

    public function mount(CreditNote $creditNote): void
    {
        $customer = authenticated_customer();

        abort_unless(
            (int) $creditNote->customer_id === (int) $customer->id,
            404,
        );

        $this->creditNote = $creditNote->load(['items', 'invoice']);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.credit-notes.show'), [
            'theme' => $theme,
            'creditNote' => $this->creditNote,
            'accountSection' => 'invoices',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.account.credit_note_title', ['number' => $this->creditNote->number]),
            'theme' => $theme,
        ]);
    }
}
