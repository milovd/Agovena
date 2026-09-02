<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Invoices;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Invoices\DeleteInvoice;
use App\Enums\InvoiceStatus;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public ?int $confirmingDeleteId = null;

    public function mount(): void
    {
        $this->authorize('invoices.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $invoiceId): void
    {
        $this->authorize('invoices.delete');
        $this->confirmingDeleteId = Invoice::query()->whereKey($invoiceId)->exists() ? $invoiceId : null;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDeleteId = null;
    }

    public function deleteInvoice(DeleteInvoice $delete): void
    {
        $this->authorize('invoices.delete');

        if ($this->confirmingDeleteId === null || ! $this->requireRecentPassword('deleteInvoice')) {
            return;
        }

        $staff = Auth::user();
        if (! $staff instanceof User) {
            abort(403);
        }

        $invoice = Invoice::query()->find($this->confirmingDeleteId);
        if ($invoice === null) {
            $this->confirmingDeleteId = null;

            return;
        }

        try {
            $delete->handle($invoice, $staff);
            $this->confirmingDeleteId = null;
            $this->resetPage();
            session()->flash('status', __('admin.invoices.deleted'));
        } catch (ValidationException $exception) {
            $this->confirmingDeleteId = null;
            session()->flash('error', $exception->errors()['invoice'][0] ?? $exception->getMessage());
        }
    }

    public function render(AdminRegistrar $admin)
    {
        $query = Invoice::query()->with('order');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term);
            });
        }

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        $confirmingInvoice = $this->confirmingDeleteId === null
            ? null
            : Invoice::query()->find($this->confirmingDeleteId);

        return view('livewire.admin.invoices.index', [
            'invoices' => $query->orderByDesc('id')->paginate(15),
            'statuses' => InvoiceStatus::cases(),
            'confirmingInvoice' => $confirmingInvoice,
        ])->layout('layouts.admin', [
            'title' => __('admin.invoices.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
