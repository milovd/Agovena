<?php

declare(strict_types=1);

namespace App\Http\Controllers\Support;

use App\Agovena\Support\TicketAttachmentPolicy;
use App\Models\TicketMessageAttachment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TicketAttachmentDownloadController
{
    use AuthorizesRequests;

    public function __invoke(Request $request, TicketMessageAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing(['message.ticket.customer']);
        $message = $attachment->message;
        abort_if($message === null, 404);
        $ticket = $message->ticket;
        abort_if($ticket === null, 404);

        $user = $request->user();
        abort_if($user === null, 403);

        $customer = $ticket->customer;
        $isOwner = $customer !== null && (int) $customer->user_id === (int) $user->id;
        $isStaff = $user->can('tickets.view') || $user->can('tickets.manage');

        abort_unless($isOwner || $isStaff, 403);

        // Customers never download attachments on internal staff notes.
        if (! $isStaff && $message->is_internal) {
            abort(404);
        }

        abort_unless(
            $attachment->disk === TicketAttachmentPolicy::DISK
            && Storage::disk($attachment->disk)->exists($attachment->path),
            404,
        );

        $downloadName = TicketAttachmentPolicy::safeDownloadName(
            $attachment->original_filename,
            $attachment->extension,
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $downloadName, [
            'Content-Type' => TicketAttachmentPolicy::safeMime((string) $attachment->mime),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename="'.$downloadName.'"',
        ]);
    }
}
