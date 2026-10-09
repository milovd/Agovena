<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Settings\SettingsRepository;
use Illuminate\Support\Facades\Storage;

/**
 * Stores the invoice design in the `invoices` settings group and resolves the store logo for documents.
 */
final class InvoiceDesignRepository
{
    public const GROUP = 'invoices';

    /** Session key for the unsaved design the Admin is previewing as PDF. */
    public const DRAFT_SESSION_KEY = 'agovena.invoice_design_draft';

    private const LOGO_TYPES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];

    private const LOGO_MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function current(): InvoiceDesign
    {
        return $this->design($this->values());
    }

    /** @param  array<string, mixed>  $values */
    public function design(array $values): InvoiceDesign
    {
        return InvoiceDesign::fromValues($values, $this->logo());
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        $stored = [];
        foreach (array_keys(InvoiceDesign::defaults()) as $key) {
            $value = $this->settings->get(self::GROUP, $key);
            if ($value !== null) {
                $stored[$key] = $value;
            }
        }

        return InvoiceDesign::normalize($stored);
    }

    /** @param  array<string, mixed>  $values */
    public function save(array $values): void
    {
        $this->settings->setMany(self::GROUP, InvoiceDesign::normalize($values));
    }

    /** The store logo from the branding settings as a data URI, so PDFs embed it without a web request. */
    public function logo(): ?string
    {
        $path = $this->settings->get('branding', 'logo_path');
        if (! is_string($path) || $path === '' || str_contains($path, '..')) {
            return null;
        }

        $type = self::LOGO_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
        $disk = Storage::disk('public');
        if ($type === null || ! $disk->exists($path) || $disk->size($path) > self::LOGO_MAX_BYTES) {
            return null;
        }

        $contents = $disk->get($path);

        return $contents === null ? null : 'data:'.$type.';base64,'.base64_encode($contents);
    }
}
