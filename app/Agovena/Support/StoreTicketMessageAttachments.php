<?php

declare(strict_types=1);

namespace App\Agovena\Support;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreTicketMessageAttachments
{
    /**
     * @param  list<UploadedFile|TemporaryUploadedFile>  $uploads
     * @return list<TicketMessageAttachment>
     */
    public function handle(Ticket $ticket, TicketMessage $message, array $uploads): array
    {
        if ($uploads === []) {
            return [];
        }

        if (count($uploads) > TicketAttachmentPolicy::MAX_FILES) {
            throw ValidationException::withMessages([
                'attachments' => [__('customer.tickets.attachments_too_many', ['max' => TicketAttachmentPolicy::MAX_FILES])],
            ]);
        }

        $stored = [];
        foreach ($uploads as $upload) {
            TicketAttachmentPolicy::assertSafe($upload);

            $extension = strtolower((string) ($upload->guessExtension() ?: $upload->getClientOriginalExtension()));
            $mime = strtolower((string) $upload->getMimeType());
            $original = (string) $upload->getClientOriginalName();
            $checksum = hash_file('sha256', $upload->getRealPath() ?: $upload->path()) ?: null;

            $path = $upload->storeAs(
                'tickets/'.$ticket->id,
                Str::uuid()->toString().'.'.$extension,
                TicketAttachmentPolicy::DISK,
            );

            $stored[] = TicketMessageAttachment::query()->create([
                'ticket_message_id' => $message->id,
                'disk' => TicketAttachmentPolicy::DISK,
                'path' => $path,
                'original_filename' => TicketAttachmentPolicy::safeDownloadName($original, $extension),
                'mime' => $mime,
                'extension' => $extension,
                'size' => (int) $upload->getSize(),
                'checksum' => $checksum,
            ]);
        }

        return $stored;
    }

    public function delete(TicketMessageAttachment $attachment): void
    {
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }
}
