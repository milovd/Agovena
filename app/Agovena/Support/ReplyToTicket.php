<?php

declare(strict_types=1);

namespace App\Agovena\Support;

use App\Agovena\Audit\AuditLogger;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class ReplyToTicket
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly StoreTicketMessageAttachments $storeAttachments,
    ) {}

    /**
     * @param  list<UploadedFile|TemporaryUploadedFile>  $attachments
     */
    public function byCustomer(Ticket $ticket, Customer $customer, string $body, array $attachments = []): TicketMessage
    {
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        return $this->store($ticket, 'customer', $customer->id, $body, false, $attachments);
    }

    /**
     * @param  list<UploadedFile|TemporaryUploadedFile>  $attachments
     */
    public function byStaff(Ticket $ticket, User $staff, string $body, bool $isInternal = false, array $attachments = []): TicketMessage
    {
        $message = $this->store($ticket, 'staff', $staff->id, $body, $isInternal, $attachments);
        $this->audit->log($isInternal ? 'ticket.internal_note_added' : 'ticket.replied', $ticket);

        if (! $isInternal) {
            $ticket->customer()->firstOrFail()->notify(new TicketRepliedNotification($ticket));
        }

        return $message;
    }

    /**
     * @param  list<UploadedFile|TemporaryUploadedFile>  $attachments
     */
    private function store(
        Ticket $ticket,
        string $authorType,
        int $authorId,
        string $body,
        bool $isInternal,
        array $attachments,
    ): TicketMessage {
        return DB::transaction(function () use ($ticket, $authorType, $authorId, $body, $isInternal, $attachments): TicketMessage {
            $message = new TicketMessage([
                'author_type' => $authorType,
                'author_id' => $authorId,
                'body' => trim($body),
                'is_internal' => $isInternal,
            ]);
            $ticket->messages()->save($message);
            $this->storeAttachments->handle($ticket, $message, $attachments);

            $updates = ['last_reply_at' => now()];
            if (! $isInternal) {
                $updates['status'] = $authorType === 'staff'
                    ? TicketStatus::Answered
                    : TicketStatus::Open;
            }
            $ticket->update($updates);

            return $message->load('attachments');
        });
    }
}
