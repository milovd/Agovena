<?php

declare(strict_types=1);

namespace App\Livewire\Admin\CreditNotes;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Invoices\IssueCreditNote;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class Create extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Invoice $invoice;

    public string $mode = 'full';

    public string $reason = '';

    /** @var array<string, string> */
    public array $quantities = [];

    public bool $confirming = false;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('invoices.credit');
        abort_unless($invoice->canIssueCreditNote(), 404);

        $this->invoice = $invoice->load('items');
        foreach ($this->invoice->creditableItems() as $item) {
            $this->quantities[(string) $item->id] = '';
        }
    }

    public function startConfirm(): void
    {
        $this->authorize('invoices.credit');
        $this->validateReason();
        $this->confirming = true;
    }

    public function cancelConfirm(): void
    {
        $this->confirming = false;
    }

    public function issue(IssueCreditNote $action): mixed
    {
        $this->authorize('invoices.credit');
        $this->validateReason();

        if (! $this->requireRecentPassword('issue')) {
            return null;
        }

        /** @var User $staff */
        $staff = Auth::user();

        $quantities = $this->mode === 'full' ? null : $this->parsedQuantities();
        $creditNote = $action->handle($this->invoice, $staff, $this->reason, $quantities);

        session()->flash('status', __('admin.credit_notes.issued'));

        return $this->redirect(route('admin.credit-notes.show', $creditNote), navigate: true);
    }

    public function render(AdminRegistrar $admin)
    {
        $this->invoice->loadMissing('items');

        return view('livewire.admin.credit-notes.create', [
            'invoice' => $this->invoice,
            'creditableItems' => $this->invoice->creditableItems(),
        ])->layout('layouts.admin', [
            'title' => __('admin.credit_notes.create_title', ['number' => $this->invoice->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function validateReason(): void
    {
        $this->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'mode' => ['required', 'in:full,partial'],
        ]);
    }

    /** @return array<int, int> */
    private function parsedQuantities(): array
    {
        $out = [];
        foreach ($this->quantities as $id => $value) {
            $qty = (int) $value;
            if ($qty > 0) {
                $out[(int) $id] = $qty;
            }
        }

        return $out;
    }
}
