<?php

declare(strict_types=1);

namespace App\Agovena\Invoices;

use App\Agovena\Theme\ThemeManager;

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

        foreach ([$this->themes->active(), $this->themes->find('default')] as $theme) {
            if ($theme === null) {
                continue;
            }

            $name = $theme->view('invoices.document');
            if (view()->exists($name)) {
                return $name;
            }
        }

        return 'invoices.document';
    }
}
