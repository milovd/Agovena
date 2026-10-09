<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Invoices;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Audit\AuditLogger;
use App\Agovena\Invoices\InvoiceDesign;
use App\Agovena\Invoices\InvoiceDesignRepository;
use App\Agovena\Invoices\InvoiceDocumentView;
use App\Agovena\Invoices\SampleInvoice;
use App\Agovena\Settings\SettingsRepository;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin > Invoices > Design: template, brand color and printed details, with a live preview.
 */
final class Design extends Component
{
    use AuthorizesRequests;

    public const PERMISSION = 'invoices.design';

    /** @var array<string, mixed> */
    public array $design = [];

    /** Which sample document the preview shows: `invoice` or `credit-note`. */
    public string $previewDocument = 'invoice';

    public function mount(InvoiceDesignRepository $designs): void
    {
        $this->authorize(self::PERMISSION);

        $this->design = $designs->values();
    }

    public function selectTemplate(string $template): void
    {
        $this->authorize(self::PERMISSION);

        if (in_array($template, InvoiceDesign::TEMPLATES, true)) {
            $this->design['template'] = $template;
        }
    }

    public function selectColor(string $color): void
    {
        $this->authorize(self::PERMISSION);

        if (in_array($color, InvoiceDesign::COLOR_PRESETS, true)) {
            $this->design['accent_color'] = $color;
        }
    }

    public function save(InvoiceDesignRepository $designs, AuditLogger $audit): void
    {
        $this->authorize(self::PERMISSION);

        $this->validate(InvoiceDesign::rules(), [], $this->attributeNames());

        $before = $designs->values();
        $designs->save($this->design);
        $after = $designs->values();
        $this->design = $after;

        $audit->log(
            'invoices.design.updated',
            null,
            ['changed' => array_keys(array_diff_assoc(array_map('strval', $after), array_map('strval', $before)))],
            category: 'invoices',
        );

        session()->flash('status', __('admin.invoice_design.saved'));
    }

    public function resetDesign(InvoiceDesignRepository $designs): void
    {
        $this->authorize(self::PERMISSION);

        $this->resetValidation();
        $this->design = $designs->values();
    }

    public function downloadPreviewPdf(InvoiceDesignRepository $designs, SampleInvoice $sample): StreamedResponse
    {
        $this->authorize(self::PERMISSION);

        $design = $designs->design($this->design);
        $pdf = $this->previewDocument === 'credit-note'
            ? Pdf::loadView('credit-notes.document', ['creditNote' => $sample->makeCreditNote(), 'printable' => false, 'design' => $design])
            : Pdf::loadView('invoices.document', ['invoice' => $sample->make(), 'printable' => false, 'design' => $design]);
        $output = $pdf->setPaper('a4')->output();

        return response()->streamDownload(static function () use ($output): void {
            echo $output;
        }, 'invoice-design-preview.pdf', ['Content-Type' => 'application/pdf']);
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        $names = [];
        foreach (array_keys(InvoiceDesign::defaults()) as $key) {
            $names['design.'.$key] = __('admin.invoice_design.fields.'.$key);
        }

        return $names;
    }

    public function render(
        AdminRegistrar $admin,
        InvoiceDesignRepository $designs,
        SampleInvoice $sample,
        SettingsRepository $settings,
        InvoiceDocumentView $documentView,
    ) {
        $this->authorize(self::PERMISSION);

        $design = $designs->design($this->design);
        $previewHtml = $this->previewDocument === 'credit-note'
            ? view('credit-notes.document', ['creditNote' => $sample->makeCreditNote(), 'printable' => false, 'design' => $design, 'mode' => 'preview'])->render()
            : view('invoices.document', ['invoice' => $sample->make(), 'printable' => false, 'design' => $design, 'mode' => 'preview'])->render();

        return view('livewire.admin.invoices.design', [
            'templates' => InvoiceDesign::TEMPLATES,
            'colorPresets' => InvoiceDesign::COLOR_PRESETS,
            'toggles' => array_keys(InvoiceDesign::TOGGLES),
            'previewHtml' => $previewHtml,
            'currentDesign' => $design,
            'hasLogo' => $design->logo !== null,
            'sellerName' => trim((string) $settings->get('store', 'seller_name', '')),
            'themeOverride' => $documentView->themeOverride(),
        ])->layout('layouts.admin', [
            'title' => __('admin.invoice_design.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }
}
