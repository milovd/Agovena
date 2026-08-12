<?php

declare(strict_types=1);

namespace App\Agovena\Support;

use App\Agovena\Audit\AuditLogger;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\StaffUser;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Support\Facades\DB;

final class ReplyToTicket
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function byCustomer(Ticket $ticket, Customer $customer, string $body): TicketMessage
    {
        abort_unless((int) $ticket->customer_id === (int) $customer->id, 404);

        return $this->store($ticket, 'customer', $customer->id, $body, false);
    }

    public function byStaff(Ticket $ticket, StaffUser $staff, string $body, bool $isInternal = false): TicketMessage
    {
        $message = $this->store($ticket, 'staff', $staff->id, $body, $isInternal);
        $this->audit->log($isInternal ? 'ticket.internal_note_added' : 'ticket.replied', $ticket);

        if (! $isInternal) {
            $ticket->customer()->firstOrFail()->notify(new TicketRepliedNotification($ticket));
        }

        return $message;
    }

    private function store(
        Ticket $ticket,
        string $authorType,
        int $authorId,
        string $body,
        bool $isInternal,
    ): TicketMessage {
        return DB::transaction(function () use ($ticket, $authorType, $authorId, $body, $isInternal): TicketMessage {
            $message = new TicketMessage([
                'author_type' => $authorType,
                'author_id' => $authorId,
                'body' => trim($body),
                'is_internal' => $isInternal,
            ]);
            $ticket->messages()->save($message);

            $updates = ['last_reply_at' => now()];
            if (! $isInternal) {
                $updates['status'] = $authorType === 'staff'
                    ? TicketStatus::Answered
                    : TicketStatus::Open;
            }
            $ticket->update($updates);

            return $message;
        });
    }
}
