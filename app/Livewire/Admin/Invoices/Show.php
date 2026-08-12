<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Invoices;

use App\Agovena\Admin\AdminRegistrar;
use App\Models\Invoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

final class Show extends Component
{
    use AuthorizesRequests;

    public Invoice $invoice;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('invoices.view');
        $this->invoice = $invoice->load(['items', 'order']);
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.invoices.show', [
            'invoice' => $this->invoice,
        ])->layout('layouts.admin', [
            'title' => __('admin.invoices.show_title', ['number' => $this->invoice->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
