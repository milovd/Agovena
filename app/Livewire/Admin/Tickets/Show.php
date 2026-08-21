<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Tickets;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Support\ReplyToTicket;
use App\Agovena\Support\TicketAttachmentPolicy;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

final class Show extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Ticket $ticket;

    public string $reply = '';

    public bool $is_internal = false;

    public string $status = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

    public function mount(Ticket $ticket): void
    {
        $this->authorize('tickets.view');
        $this->ticket = $ticket;
        $this->status = $ticket->status->value;
    }

    public function sendReply(ReplyToTicket $replyToTicket): void
    {
        $this->authorize('tickets.manage');
        $this->validate(array_merge([
            'reply' => ['required', 'string', 'max:20000'],
            'is_internal' => ['boolean'],
        ], TicketAttachmentPolicy::validationRules()));
        /** @var User $staff */
        $staff = Auth::user();
        $replyToTicket->byStaff(
            $this->ticket,
            $staff,
            $this->reply,
            $this->is_internal,
            array_values($this->attachments),
        );
        $this->reset(['reply', 'is_internal', 'attachments']);
        $this->ticket->refresh();
        $this->status = $this->ticket->status->value;
        session()->flash('status', __('admin.tickets.reply_sent'));
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function updateStatus(): void
    {
        $this->authorize('tickets.manage');
        $data = $this->validate(['status' => ['required', Rule::enum(TicketStatus::class)]]);
        $this->ticket->update(['status' => $data['status']]);
        session()->flash('status', __('admin.tickets.status_updated'));
    }

    public function assignSelf(): void
    {
        $this->authorize('tickets.manage');
        $this->ticket->update(['staff_user_id' => Auth::id()]);
        session()->flash('status', __('admin.tickets.assigned'));
    }

    public function render(AdminRegistrar $admin)
    {
        $this->ticket->load(['customer', 'assignee', 'messages.attachments']);

        return view('livewire.admin.tickets.show', [
            'ticket' => $this->ticket,
            'maxAttachments' => TicketAttachmentPolicy::MAX_FILES,
            'maxKilobytes' => TicketAttachmentPolicy::MAX_KILOBYTES,
        ])->layout('layouts.admin', [
            'title' => __('admin.tickets.ticket_title', ['number' => $this->ticket->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
