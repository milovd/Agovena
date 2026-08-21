<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Support\CreateTicket;
use App\Agovena\Support\TicketAttachmentPolicy;
use App\Agovena\Theme\ThemeManager;
use App\Enums\TicketPriority;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

final class TicketCreate extends Component
{
    use WithFileUploads;

    public string $subject = '';

    public string $body = '';

    public string $priority = 'normal';

    public ?int $order_id = null;

    /** @var array<int, mixed> */
    public array $attachments = [];

    public function save(CreateTicket $createTicket): void
    {
        $data = $this->validate(array_merge([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'order_id' => ['nullable', 'integer'],
        ], TicketAttachmentPolicy::validationRules()));

        /** @var Customer $customer */
        $customer = authenticated_customer();
        $ticket = $createTicket->handle(
            $customer,
            $data['subject'],
            $data['body'],
            TicketPriority::from($data['priority']),
            orderId: $data['order_id'],
            attachments: array_values($this->attachments),
        );

        $this->reset('attachments');
        session()->flash('status', __('customer.tickets.created'));
        $this->redirect(route('customer.tickets.show', $ticket), navigate: true);
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        /** @var Customer $customer */
        $customer = authenticated_customer();

        return view($theme->view('account.tickets.create'), [
            'orders' => $customer->orders()->latest()->get(['id', 'number']),
            'accountSection' => 'tickets',
            'maxAttachments' => TicketAttachmentPolicy::MAX_FILES,
            'maxKilobytes' => TicketAttachmentPolicy::MAX_KILOBYTES,
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.tickets.create_title'),
            'theme' => $theme,
        ]);
    }
}
