<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Tickets;

use App\Agovena\Admin\AdminRegistrar;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $status = '';

    public function mount(): void
    {
        $this->authorize('tickets.view');
    }

    public function updatedStatus(): void
    {
        $this->validateOnly('status', ['status' => ['nullable', Rule::enum(TicketStatus::class)]]);
        $this->resetPage();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.tickets.index', [
            'tickets' => Ticket::query()
                ->with(['customer', 'assignee'])
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->latest('last_reply_at')
                ->paginate(20),
        ])->layout('layouts.admin', [
            'title' => __('admin.tickets.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
