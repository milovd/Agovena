<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CreditNotes;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\CreditNote;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;

    public CreditNote $creditNote;

    public function mount(CreditNote $creditNote): void
    {
        $this->authorize('invoices.view');
        $this->creditNote = $creditNote->load(['items', 'invoice', 'order', 'creator']);
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.credit-notes.show', [
            'creditNote' => $this->creditNote,
        ])->layout('layouts.admin', [
            'title' => __('admin.credit_notes.show_title', ['number' => $this->creditNote->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
