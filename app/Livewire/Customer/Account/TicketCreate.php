<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Support\CreateTicket;
use App\Agovena\Theme\ThemeManager;
use App\Enums\TicketPriority;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class TicketCreate extends Component
{
    public string $subject = '';

    public string $body = '';

    public string $priority = 'normal';

    public ?int $order_id = null;

    public function save(CreateTicket $createTicket): void
    {
        $data = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'order_id' => ['nullable', 'integer'],
        ]);
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();
        $ticket = $createTicket->handle(
            $customer,
            $data['subject'],
            $data['body'],
            TicketPriority::from($data['priority']),
            orderId: $data['order_id'],
        );

        session()->flash('status', __('customer.tickets.created'));
        $this->redirect(route('customer.tickets.show', $ticket), navigate: true);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return view($theme->view('account.tickets.create'), [
            'orders' => $customer->orders()->latest()->get(['id', 'number']),
            'accountSection' => 'tickets',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.tickets.create_title'),
            'theme' => $theme,
        ]);
    }
}
