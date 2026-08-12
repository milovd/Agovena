<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Support\ReplyToTicket;
use App\Agovena\Theme\ThemeManager;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class TicketShow extends Component
{
    public Ticket $ticket;

    public string $reply = '';

    public function mount(Ticket $ticket): void
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);
        $this->ticket = $ticket;
    }

    public function reply(ReplyToTicket $replyToTicket): void
    {
        abort_if($this->ticket->status === TicketStatus::Closed, 422);
        $data = $this->validate(['reply' => ['required', 'string', 'max:20000']]);
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $replyToTicket->byCustomer($this->ticket, $customer, $data['reply']);
        $this->reply = '';
        session()->flash('status', __('customer.tickets.reply_sent'));
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        $this->ticket->load([
            'messages' => fn ($query) => $query->where('is_internal', false)->oldest(),
        ]);

        return view($theme->view('account.tickets.show'), [
            'ticket' => $this->ticket,
            'accountSection' => 'tickets',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.tickets.ticket_title', ['number' => $this->ticket->number]),
            'theme' => $theme,
        ]);
    }
}
