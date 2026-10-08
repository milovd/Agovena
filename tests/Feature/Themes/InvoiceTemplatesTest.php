<?php

declare(strict_types=1);

use App\Agovena\Invoices\RenderCreditNoteDocument;
use App\Agovena\Invoices\RenderInvoiceDocument;
use App\Agovena\Theme\ThemeManager;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Livewire\Admin\Appearance\Customize;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

dataset('invoice templates', ['classic', 'modern', 'minimal', 'compact']);

function invoiceTemplateInvoice(): Invoice
{
    $customer = Customer::factory()->create(['name' => 'Current Name']);
    $invoice = Invoice::query()->create([
        'number' => 'INV-TPL-00042',
        'status' => InvoiceStatus::Paid,
        'customer_id' => $customer->id,
        'customer_name' => 'Snapshot Customer',
        'customer_email' => 'buyer@example.test',
        'billing_name' => 'Billing Person',
        'billing_company' => 'Buyer Holding BV',
        'billing_line1' => 'Keizersgracht 1',
        'billing_line2' => 'Floor 4',
        'billing_city' => 'Amsterdam',
        'billing_region' => 'Noord-Holland',
        'billing_postal_code' => '1015 CJ',
        'billing_country' => 'NL',
        'billing_phone' => '+31 20 555 0101',
        'merchant_name' => 'Seller Trading BV',
        'merchant_address' => "Seller Street 9\n3511 AA Utrecht\nVAT NL123456789B01\nCoC 87654321",
        'custom_properties_snapshot' => [
            ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL999999999B09'],
        ],
        'issued_at' => '2026-03-14',
        'paid_at' => '2026-03-16 10:00:00',
        'subtotal_amount' => 12500,
        'discount_amount' => 1500,
        'credit_amount' => 700,
        'tax_amount' => 2163,
        'tax_rate_name' => 'VAT 21%',
        'tax_rate_bps' => 2100,
        'total_amount' => 12463,
        'currency' => 'EUR',
    ]);

    $invoice->items()->create([
        'kind' => InvoiceItemKind::Product,
        'label' => 'Managed VPS',
        'quantity' => 2,
        'unit_amount' => 5000,
        'line_total_amount' => 10000,
        'currency' => 'EUR',
        'options_snapshot' => [['key' => 'ram', 'label' => 'RAM', 'display' => '8 GB']],
    ]);
    $invoice->items()->create([
        'kind' => InvoiceItemKind::Shipping,
        'label' => 'Setup service',
        'quantity' => 1,
        'unit_amount' => 2500,
        'line_total_amount' => 2500,
        'currency' => 'EUR',
    ]);
    $invoice->items()->create([
        'kind' => InvoiceItemKind::Discount,
        'label' => 'Spring promotion',
        'quantity' => 1,
        'unit_amount' => 1500,
        'line_total_amount' => 1500,
        'currency' => 'EUR',
    ]);

    return $invoice->fresh();
}

function invoiceTemplateCreditNote(): CreditNote
{
    $invoice = invoiceTemplateInvoice();
    $creditNote = CreditNote::query()->create([
        'number' => 'CN-TPL-00007',
        'status' => CreditNoteStatus::Issued,
        'invoice_id' => $invoice->id,
        'customer_id' => $invoice->customer_id,
        'customer_name' => $invoice->customer_name,
        'customer_email' => $invoice->customer_email,
        'billing_name' => $invoice->billing_name,
        'billing_company' => $invoice->billing_company,
        'billing_line1' => $invoice->billing_line1,
        'billing_line2' => $invoice->billing_line2,
        'billing_city' => $invoice->billing_city,
        'billing_region' => $invoice->billing_region,
        'billing_postal_code' => $invoice->billing_postal_code,
        'billing_country' => $invoice->billing_country,
        'billing_phone' => $invoice->billing_phone,
        'merchant_name' => $invoice->merchant_name,
        'merchant_address' => $invoice->merchant_address,
        'custom_properties_snapshot' => $invoice->custom_properties_snapshot,
        'issued_at' => '2026-04-02',
        'reason' => 'Returned within 14 days',
        'subtotal_amount' => 5000,
        'tax_amount' => 1050,
        'tax_rate_name' => 'VAT 21%',
        'tax_rate_bps' => 2100,
        'total_amount' => 6050,
        'currency' => 'EUR',
    ]);
    $creditNote->items()->create([
        'invoice_item_id' => $invoice->items->first()->id,
        'kind' => InvoiceItemKind::Product,
        'label' => 'Managed VPS',
        'quantity' => 1,
        'unit_amount' => 5000,
        'line_total_amount' => 5000,
        'currency' => 'EUR',
    ]);

    return $creditNote->fresh();
}

function selectInvoiceTemplate(string $template): void
{
    app(ThemeManager::class)->config()->set('invoices.template', $template);
}

/**
 * Collapses formatting whitespace so Blade indentation from includes does not count as a change.
 * The light-only color-scheme meta is the one intended Classic addition: it keeps browsers from
 * auto-darkening the document and does not change how it looks.
 */
function normalizedDocumentHtml(string $html): string
{
    $html = str_replace('<meta name="color-scheme" content="only light">', '', $html);
    $html = (string) preg_replace('/\s+/', ' ', $html);

    return trim((string) preg_replace('/\s*(<[^>]+>)\s*/', '$1', $html));
}

/** Visible document text only: the title and stylesheet must not satisfy a field assertion. */
function textOf(string $html): string
{
    $body = (string) preg_replace('#<(style|title)\b[^>]*>.*?</\1>#s', '', $html);

    return normalizedDocumentHtml(html_entity_decode(strip_tags(str_replace('<', ' <', $body)), ENT_QUOTES | ENT_HTML5));
}

test('every invoice template renders all invoice data and a printable A4 pdf', function (string $template) {
    selectInvoiceTemplate($template);
    $invoice = invoiceTemplateInvoice();

    $html = app(RenderInvoiceDocument::class)->html($invoice);
    $text = textOf($html);

    foreach ([
        'INV-TPL-00042',
        __('invoices.status.paid'),
        __('invoices.issued'), '2026-03-14',
        __('invoices.paid_on'), '2026-03-16',
        __('invoices.seller'), 'Seller Trading BV', 'Seller Street 9', 'VAT NL123456789B01', 'CoC 87654321',
        __('invoices.bill_to'), 'Billing Person', 'Buyer Holding BV', 'Keizersgracht 1', 'Floor 4',
        '1015 CJ Amsterdam', 'Noord-Holland', 'NL', '+31 20 555 0101', 'buyer@example.test',
        'VAT number: NL999999999B09',
        __('invoices.item'), __('invoices.qty'), __('invoices.amount'),
        'Managed VPS', 'RAM: 8 GB', 'Setup service', 'Spring promotion',
        '−€15.00',
        __('common.subtotal'), '€125.00',
        __('common.discount'), '−€15.00',
        __('common.credit'), '−€7.00',
        'VAT 21%', '€21.63',
        __('common.total'), '€124.63',
        __('invoices.print'),
    ] as $expected) {
        expect($text)->toContain($expected);
    }

    expect($text)->not->toContain('Current Name')
        ->and($html)->toContain('@media print')
        ->and(substr(app(RenderInvoiceDocument::class)->pdf($invoice), 0, 5))->toBe('%PDF-');

    if ($template !== 'classic') {
        expect($html)->toContain('invoice-doc--'.$template)
            ->toContain('size: A4')
            ->toContain('color-scheme: only light');
    }
})->with('invoice templates');

test('every invoice template renders all credit note data and a printable A4 pdf', function (string $template) {
    selectInvoiceTemplate($template);
    $creditNote = invoiceTemplateCreditNote();

    $html = app(RenderCreditNoteDocument::class)->html($creditNote);
    $text = textOf($html);

    foreach ([
        'CN-TPL-00007',
        __('credit_notes.document_title'),
        __('invoices.issued'), '2026-04-02',
        __('credit_notes.related_invoice'), 'INV-TPL-00042',
        __('credit_notes.reason'), 'Returned within 14 days',
        __('invoices.seller'), 'Seller Trading BV', 'Seller Street 9', 'VAT NL123456789B01', 'CoC 87654321',
        __('invoices.bill_to'), 'Billing Person', 'Buyer Holding BV', 'Keizersgracht 1', 'Floor 4',
        '1015 CJ Amsterdam', 'Noord-Holland', 'NL', '+31 20 555 0101', 'buyer@example.test',
        'VAT number: NL999999999B09',
        __('invoices.item'), __('invoices.qty'), __('invoices.amount'),
        'Managed VPS',
        __('common.subtotal'), '€50.00',
        'VAT 21%', '€10.50',
        __('common.total'), '€60.50',
    ] as $expected) {
        expect($text)->toContain($expected);
    }

    expect($text)->not->toContain(__('invoices.paid_on'))
        ->and(substr(app(RenderCreditNoteDocument::class)->pdf($creditNote), 0, 5))->toBe('%PDF-');

    if ($template !== 'classic') {
        expect($html)->toContain('invoice-doc--'.$template);
    }
})->with('invoice templates');

test('the classic template keeps the original invoice and credit note output', function () {
    $creditNote = invoiceTemplateCreditNote();
    $invoice = $creditNote->invoice->load('items');

    $originalInvoice = view('invoices.document', ['invoice' => $invoice, 'printable' => true])->render();
    $originalCreditNote = view('credit-notes.document', ['creditNote' => $creditNote, 'printable' => true])->render();

    expect(app(RenderInvoiceDocument::class)->html($invoice))->toContain('<meta name="color-scheme" content="only light">')
        ->and(normalizedDocumentHtml(app(RenderInvoiceDocument::class)->html($invoice)))
        ->toBe(normalizedDocumentHtml($originalInvoice))
        ->and(normalizedDocumentHtml(app(RenderCreditNoteDocument::class)->html($creditNote)))
        ->toBe(normalizedDocumentHtml($originalCreditNote));

    $originalPdfView = view('invoices.document', ['invoice' => $invoice, 'printable' => false])->render();
    expect(normalizedDocumentHtml(view('theme::invoices.document', ['invoice' => $invoice, 'printable' => false])->render()))
        ->toBe(normalizedDocumentHtml($originalPdfView));
});

test('unknown or removed invoice templates fall back to classic', function () {
    selectInvoiceTemplate('retro-2019');
    $invoice = invoiceTemplateInvoice();

    expect(normalizedDocumentHtml(app(RenderInvoiceDocument::class)->html($invoice)))
        ->toBe(normalizedDocumentHtml(view('invoices.document', ['invoice' => $invoice, 'printable' => true])->render()));
});

test('merchant selects the invoice template in the theme customizer and documents use it', function () {
    $staff = $this->createStaff();

    $this->actingAs($staff)
        ->get('/admin/appearance/customize')
        ->assertOk();

    Livewire::actingAs($staff)
        ->test(Customize::class)
        ->set('tab', 'storefront')
        ->assertSee(__('admin.appearance.theme_fields.invoices.template'))
        ->assertSee(__('admin.appearance.theme_options.invoices.template.modern'))
        ->set('values.invoices.template', 'minimal')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(ThemeManager::class)->config()->string('invoices.template'))->toBe('minimal');

    $invoice = invoiceTemplateInvoice();
    $this->actingAs($staff)
        ->get(route('admin.invoices.print', $invoice))
        ->assertOk()
        ->assertSee('invoice-doc--minimal', false);

    Livewire::actingAs($staff)
        ->test(Customize::class)
        ->set('values.invoices.template', 'retro-2019')
        ->call('save')
        ->assertHasErrors(['values.invoices.template']);

    expect(app(ThemeManager::class)->config()->string('invoices.template'))->toBe('minimal');
});

test('a third party theme that provides its own invoice document is unaffected by the template setting', function () {
    $views = storage_path('framework/testing/third-party-theme-views');
    File::ensureDirectoryExists($views.'/invoices');
    File::put($views.'/invoices/document.blade.php', '<p>Third party {{ $invoice->number }}</p>');
    View::replaceNamespace('theme', $views);
    View::getFinder()->flush();

    try {
        app(ThemeManager::class)->config()->set('invoices.template', 'modern');
        $creditNote = invoiceTemplateCreditNote();
        $invoice = $creditNote->invoice;

        expect(app(RenderInvoiceDocument::class)->html($invoice))->toBe('<p>Third party INV-TPL-00042</p>')
            ->and(normalizedDocumentHtml(app(RenderCreditNoteDocument::class)->html($creditNote)))
            ->toBe(normalizedDocumentHtml(view('credit-notes.document', ['creditNote' => $creditNote, 'printable' => true])->render()));
    } finally {
        File::deleteDirectory($views);
    }
});
