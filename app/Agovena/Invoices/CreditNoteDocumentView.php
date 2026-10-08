<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Theme\ThemeManager;

/**
 * Credit notes use the active Theme's optional `invoices.credit-note` view so
 * they can share its invoice layout. Themes without one keep the core document.
 */
final class CreditNoteDocumentView
{
    public function __construct(private readonly ThemeManager $themes) {}

    public function name(): string
    {
        $name = $this->themes->active()->view('invoices.credit-note');

        return view()->exists($name) ? $name : 'credit-notes.document';
    }
}
