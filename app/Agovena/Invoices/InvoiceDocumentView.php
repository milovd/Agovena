<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Theme\ThemeManager;

/**
 * Core renders invoices with the merchant's invoice design. An active Theme that ships its own
 * `invoices.document` view still replaces it, which keeps the Theme contract of earlier releases.
 */
final class InvoiceDocumentView
{
    private ?string $override = null;

    public function __construct(private readonly ThemeManager $themes) {}

    public function use(string $view): void
    {
        $this->override = $view;
    }

    public function name(): string
    {
        if ($this->override !== null) {
            return $this->override;
        }

        return $this->themeOverride() ?? 'invoices.document';
    }

    /** The active Theme's own invoice view, when it ships one. */
    public function themeOverride(): ?string
    {
        $name = $this->themes->active()->view('invoices.document');

        return view()->exists($name) ? $name : null;
    }
}
