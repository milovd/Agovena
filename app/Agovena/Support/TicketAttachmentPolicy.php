<?php

declare(strict_types=1);

namespace App\Agovena\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Hardening against Paymenter-style ticket upload RCE (CVE-2025-58048):
 * whitelist only, private disk, never trust client MIME/filename alone.
 */
final class TicketAttachmentPolicy
{
    public const DISK = 'local';

    public const MAX_FILES = 5;

    /** Kilobytes — Laravel file max rule unit. */
    public const MAX_KILOBYTES = 5120;

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    /** @var list<string> */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
    ];

    /** @var list<string> */
    private const BLOCKED_NAME_FRAGMENTS = [
        '.php', '.phtml', '.phar', '.htaccess', '.svg', '.html', '.htm', '.js', '.exe', '.sh', '.bat', '.cmd', '.asp', '.aspx', '.jsp',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function validationRules(string $field = 'attachments'): array
    {
        return [
            $field => ['nullable', 'array', 'max:'.self::MAX_FILES],
            $field.'.*' => [
                'file',
                'max:'.self::MAX_KILOBYTES,
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
                'mimetypes:'.implode(',', self::ALLOWED_MIMES),
            ],
        ];
    }

    public static function assertSafe(UploadedFile|TemporaryUploadedFile $upload): void
    {
        $original = strtolower((string) $upload->getClientOriginalName());
        foreach (self::BLOCKED_NAME_FRAGMENTS as $fragment) {
            if (str_contains($original, $fragment)) {
                throw ValidationException::withMessages([
                    'attachments' => [__('customer.tickets.attachments_blocked')],
                ]);
            }
        }

        $extension = strtolower((string) ($upload->guessExtension() ?: $upload->getClientOriginalExtension()));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'attachments' => [__('customer.tickets.attachments_invalid_type')],
            ]);
        }

        $mime = strtolower((string) ($upload->getMimeType() ?: ''));
        if (! in_array($mime, self::ALLOWED_MIMES, true) || str_contains($mime, 'svg')) {
            throw ValidationException::withMessages([
                'attachments' => [__('customer.tickets.attachments_invalid_type')],
            ]);
        }
    }

    public static function safeDownloadName(string $original, string $extension): string
    {
        $base = pathinfo($original, PATHINFO_FILENAME);
        $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $base) ?: 'attachment';
        $base = trim($base, '.-_') ?: 'attachment';

        return $base.'.'.$extension;
    }
}
