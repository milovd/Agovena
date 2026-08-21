<?php

declare(strict_types=1);

namespace App\Agovena\Support;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class CreateTicket
{
    public function __construct(
        private readonly StoreTicketMessageAttachments $storeAttachments,
    ) {}

    /**
     * @param  list<UploadedFile|TemporaryUploadedFile>  $attachments
     */
    public function handle(
        Customer $customer,
        string $subject,
        string $body,
        TicketPriority $priority = TicketPriority::Normal,
        ?string $department = null,
        ?int $orderId = null,
        array $attachments = [],
    ): Ticket {
        if ($orderId !== null && ! $customer->orders()->whereKey($orderId)->exists()) {
            abort(404);
        }

        return DB::transaction(function () use ($customer, $subject, $body, $priority, $department, $orderId, $attachments): Ticket {
            do {
                $number = 'TKT-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
            } while (Ticket::query()->where('number', $number)->exists());

            $ticket = Ticket::query()->create([
                'number' => $number,
                'customer_id' => $customer->id,
                'subject' => trim($subject),
                'status' => TicketStatus::Open,
                'priority' => $priority,
                'department' => filled($department) ? trim((string) $department) : null,
                'order_id' => $orderId,
                'last_reply_at' => now(),
            ]);

            $message = new TicketMessage([
                'author_type' => 'customer',
                'author_id' => $customer->id,
                'body' => trim($body),
                'is_internal' => false,
            ]);
            $ticket->messages()->save($message);

            $this->storeAttachments->handle($ticket, $message, $attachments);

            return $ticket->load(['messages.attachments']);
        });
    }
}
