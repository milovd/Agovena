<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Support\ReplyToTicket;
use App\Agovena\Support\TicketAttachmentPolicy;
use App\Agovena\Theme\ThemeManager;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use Livewire\Component;
use Livewire\WithFileUploads;

final class TicketShow extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $reply = '';

    /** @var array<int, mixed> */
    public array $attachments = [];

    public function mount(Ticket $ticket): void
    {
        /** @var Customer $customer */
        $customer = authenticated_customer();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);
        $this->ticket = $ticket;
    }

    public function reply(ReplyToTicket $replyToTicket): void
    {
        abort_if($this->ticket->status === TicketStatus::Closed, 422);
        $this->validate(array_merge(
            ['reply' => ['required', 'string', 'max:20000']],
            TicketAttachmentPolicy::validationRules(),
        ));
        /** @var Customer $customer */
        $customer = authenticated_customer();
        $replyToTicket->byCustomer($this->ticket, $customer, $this->reply, array_values($this->attachments));
        $this->reset(['reply', 'attachments']);
        session()->flash('status', __('customer.tickets.reply_sent'));
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $this->ticket->load([
            'messages' => fn ($query) => $query->where('is_internal', false)->with('attachments')->oldest(),
        ]);

        return view($theme->view('account.tickets.show'), [
            'ticket' => $this->ticket,
            'accountSection' => 'tickets',
            'maxAttachments' => TicketAttachmentPolicy::MAX_FILES,
            'maxKilobytes' => TicketAttachmentPolicy::MAX_KILOBYTES,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.tickets.ticket_title', ['number' => $this->ticket->number]),
            'theme' => $theme,
        ]);
    }
}
