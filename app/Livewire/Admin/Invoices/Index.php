<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Invoices;

use App\Agovena\Admin\AdminRegistrar;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public string $status = '';

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

        return view('livewire.admin.invoices.index', [
            'invoices' => $query->orderByDesc('id')->paginate(15),
            'statuses' => InvoiceStatus::cases(),
        ])->layout('layouts.admin', [
            'title' => __('admin.invoices.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
