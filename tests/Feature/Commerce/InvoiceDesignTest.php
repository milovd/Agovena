<?php

declare(strict_types=1);

use App\Agovena\Invoices\InvoiceDesign;
use App\Agovena\Invoices\InvoiceDesignRepository;
use App\Agovena\Invoices\RenderCreditNoteDocument;
use App\Agovena\Invoices\RenderInvoiceDocument;
use App\Agovena\Settings\SettingsRepository;
use App\Agovena\Theme\ThemeManager;
use App\Enums\CreditNoteStatus;
use App\Enums\InvoiceItemKind;
use App\Enums\InvoiceStatus;
use App\Livewire\Admin\Invoices\Design;
use App\Models\AuditLog;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

dataset('invoice templates', InvoiceDesign::TEMPLATES);

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
        'merchant_address' => "Seller Street 9\n3511 AA Utrecht",
        'merchant_vat_number' => 'NL123456789B01',
        'merchant_company_number' => '87654321',
        'custom_properties_snapshot' => [
            ['key' => 'vat_number', 'label' => 'VAT number', 'value' => 'NL999999999B09'],
        ],
        'issued_at' => '2026-03-14',
        'due_at' => '2026-03-28',
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
        'merchant_vat_number' => $invoice->merchant_vat_number,
        'merchant_company_number' => $invoice->merchant_company_number,
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

/** @param  array<string, mixed>  $values */
function saveInvoiceDesign(array $values): void
{
    app(InvoiceDesignRepository::class)->save([...app(InvoiceDesignRepository::class)->values(), ...$values]);
}

/**
 * Collapses formatting whitespace so Blade indentation from includes does not count as a change.
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
    saveInvoiceDesign(['template' => $template, 'contact_email' => 'billing@seller.test', 'payment_details' => 'IBAN NL00 BANK 0123 4567 89', 'notes' => 'Thanks for your order.']);
    $invoice = invoiceTemplateInvoice();

    $html = app(RenderInvoiceDocument::class)->html($invoice);
    $text = textOf($html);

    foreach ([
        'INV-TPL-00042',
        __('invoices.issued'), '2026-03-14',
        __('invoices.paid_on'), '2026-03-16',
        __('invoices.due_on'), '2026-03-28',
        'Seller Trading BV', 'Seller Street 9',
        __('invoices.seller_vat_number'), 'NL123456789B01',
        __('invoices.seller_company_number'), '87654321',
        'billing@seller.test',
        'Billing Person', 'Buyer Holding BV', 'Keizersgracht 1', 'Floor 4',
        '1015 CJ Amsterdam', 'Noord-Holland', '+31 20 555 0101', 'buyer@example.test',
        'NL999999999B09',
        __('invoices.description'), __('invoices.qty'), __('invoices.unit_price'), __('invoices.amount'),
        'Managed VPS', 'RAM: 8 GB', '€50.00', '€100.00', 'Setup service', 'Spring promotion',
        '−€15.00', '−€7.00',
        __('common.subtotal'), '€125.00',
        'VAT 21%', '€21.63',
        '€124.63',
        'IBAN NL00 BANK 0123 4567 89', 'Thanks for your order.',
        __('invoices.print'),
    ] as $expected) {
        expect($text)->toContain($expected);
    }

    expect($text)->not->toContain('Current Name')
        ->and($html)->toContain('@media print')
        ->and($html)->toContain('inv--'.$template)
        ->and(substr(app(RenderInvoiceDocument::class)->pdf($invoice), 0, 5))->toBe('%PDF-');
})->with('invoice templates');

test('every invoice template renders all credit note data and a printable A4 pdf', function (string $template) {
    saveInvoiceDesign(['template' => $template, 'payment_details' => 'IBAN NL00 BANK 0123 4567 89']);
    $creditNote = invoiceTemplateCreditNote();

    $html = app(RenderCreditNoteDocument::class)->html($creditNote);
    $text = textOf($html);

    foreach ([
        'CN-TPL-00007',
        __('credit_notes.document_title'),
        '2026-04-02',
        __('credit_notes.related_invoice'), 'INV-TPL-00042',
        __('credit_notes.reason'), 'Returned within 14 days',
        'Seller Trading BV', 'NL123456789B01', '87654321',
        'Billing Person', 'Buyer Holding BV',
        'Managed VPS',
        __('common.subtotal'), '€50.00',
        'VAT 21%', '€10.50',
        '€60.50',
    ] as $expected) {
        expect($text)->toContain($expected);
    }

    expect($text)->not->toContain(__('invoices.paid_on'))
        ->and($text)->not->toContain(__('invoices.due_on'))
        ->and($text)->not->toContain('IBAN NL00 BANK 0123 4567 89')
        ->and(substr(app(RenderCreditNoteDocument::class)->pdf($creditNote), 0, 5))->toBe('%PDF-');
})->with('invoice templates');

test('every invoice template shows the tax rate and whether prices already include it', function (string $template) {
    saveInvoiceDesign(['template' => $template]);

    $invoice = invoiceTemplateInvoice();
    $invoice->forceFill(['tax_rate_name' => 'VAT'])->save();
    $exclusiveText = textOf(app(RenderInvoiceDocument::class)->html($invoice->fresh()));

    expect($exclusiveText)->toContain('VAT 21%')
        ->and($exclusiveText)->not->toContain(__('invoices.tax_included', ['label' => 'VAT 21%']));

    // Same lines, but the total no longer adds the tax on top: the prices already contained it.
    $invoice->forceFill(['total_amount' => 10300])->save();
    $inclusiveText = textOf(app(RenderInvoiceDocument::class)->html($invoice->fresh()));

    expect($inclusiveText)->toContain(__('invoices.tax_included', ['label' => 'VAT 21%']))
        ->and($inclusiveText)->toContain('€103.00');
})->with('invoice templates');

test('switched off details are left out of every template', function (string $template) {
    saveInvoiceDesign([
        'template' => $template,
        'contact_email' => 'billing@seller.test',
        'payment_details' => 'IBAN NL00 BANK 0123 4567 89',
        'notes' => 'Thanks for your order.',
        'show_seller_address' => false,
        'show_vat_number' => false,
        'show_company_number' => false,
        'show_contact' => false,
        'show_buyer_properties' => false,
        'show_payment_details' => false,
        'show_notes' => false,
    ]);

    $text = textOf(app(RenderInvoiceDocument::class)->html(invoiceTemplateInvoice()));

    foreach (['Seller Street 9', 'NL123456789B01', '87654321', 'billing@seller.test', 'NL999999999B09', 'IBAN NL00 BANK 0123 4567 89', 'Thanks for your order.'] as $hidden) {
        expect($text)->not->toContain($hidden);
    }

    expect($text)->toContain('Seller Trading BV')
        ->and($text)->toContain('Billing Person')
        ->and($text)->toContain('€124.63');
})->with('invoice templates');

test('details that were never recorded are not printed', function (string $template) {
    saveInvoiceDesign(['template' => $template]);
    $invoice = invoiceTemplateInvoice();
    $invoice->forceFill(['merchant_vat_number' => null, 'merchant_company_number' => null, 'due_at' => null])->save();

    $text = textOf(app(RenderInvoiceDocument::class)->html($invoice->fresh()));

    expect($text)->not->toContain(__('invoices.seller_company_number'))
        ->and($text)->not->toContain(__('invoices.due_on'))
        ->and($text)->not->toContain('2026-03-28');
})->with('invoice templates');

test('the brand color and store logo are used in every template', function (string $template) {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
    app(SettingsRepository::class)->set('branding', 'logo_path', 'branding/logo.png');
    saveInvoiceDesign(['template' => $template, 'accent_color' => '#0F766E']);

    $invoice = invoiceTemplateInvoice();
    $teal = app(RenderInvoiceDocument::class)->html($invoice);

    saveInvoiceDesign(['accent_color' => '#B91C1C']);
    $red = app(RenderInvoiceDocument::class)->html($invoice);

    expect($teal)->toContain('data:image/png;base64,')
        ->and($red)->not->toBe($teal);

    saveInvoiceDesign(['show_logo' => false]);
    expect(app(RenderInvoiceDocument::class)->html($invoice))->not->toContain('data:image/png;base64,');
})->with('invoice templates');

test('a broken stored design falls back to the defaults instead of breaking documents', function () {
    app(SettingsRepository::class)->setMany(InvoiceDesignRepository::GROUP, [
        'template' => 'retro-2019',
        'accent_color' => 'red; background: url(x)',
    ]);

    $design = app(InvoiceDesignRepository::class)->current();
    expect($design->template)->toBe(InvoiceDesign::DEFAULT_TEMPLATE)
        ->and($design->color)->toBe(InvoiceDesign::DEFAULT_COLOR);

    $html = app(RenderInvoiceDocument::class)->html(invoiceTemplateInvoice());
    expect($html)->toContain('inv--'.InvoiceDesign::DEFAULT_TEMPLATE)
        ->and($html)->not->toContain('url(x)');
});

test('readable ink is chosen for light and dark brand colors', function () {
    $repository = app(InvoiceDesignRepository::class);

    expect($repository->design(['accent_color' => '#F5C518'])->ink())->toBe('#111827')
        ->and($repository->design(['accent_color' => '#1F3A5F'])->ink())->toBe('#FFFFFF');
});

test('a theme that ships its own invoice document still replaces the design', function () {
    $staff = $this->createStaff();
    $themes = app(ThemeManager::class);
    $views = base_path('themes/default/views/invoices');
    File::ensureDirectoryExists($views);
    File::put($views.'/document.blade.php', '<p>Third party {{ $invoice->number }}</p>');

    try {
        View::flushFinderCache();
        $invoice = invoiceTemplateInvoice();
        expect(app(RenderInvoiceDocument::class)->html($invoice))->toContain('Third party INV-TPL-00042');

        Livewire::actingAs($staff)->test(Design::class)
            ->assertSee(__('admin.invoice_design.theme_override'));
    } finally {
        File::deleteDirectory($views);
        View::flushFinderCache();
    }

    expect($themes->active()->id)->toBe('default');
});

test('staff design invoices with a live preview and save the design', function () {
    $staff = $this->createStaff();

    $component = Livewire::actingAs($staff)->test(Design::class)
        ->assertSee(__('admin.invoice_design.title'))
        ->assertSee(__('admin.invoice_design.templates.banner.name'))
        ->call('selectTemplate', 'banner')
        ->call('selectColor', '#0F766E')
        ->set('design.payment_details', 'IBAN BE68 5390 0754 7034')
        ->set('design.show_vat_number', false)
        ->assertSee('inv--banner', false)
        ->assertSee('IBAN BE68 5390 0754 7034', false);

    expect(app(InvoiceDesignRepository::class)->current()->template)->toBe(InvoiceDesign::DEFAULT_TEMPLATE);

    $component->call('save')->assertHasNoErrors()->assertSee(__('admin.invoice_design.saved'));

    $design = app(InvoiceDesignRepository::class)->current();
    expect($design->template)->toBe('banner')
        ->and($design->color)->toBe('#0F766E')
        ->and($design->paymentDetails)->toBe('IBAN BE68 5390 0754 7034')
        ->and($design->shows('vat_number'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'invoices.design.updated')->exists())->toBeTrue();

    $html = app(RenderInvoiceDocument::class)->html(invoiceTemplateInvoice());
    expect($html)->toContain('inv--banner')->and($html)->not->toContain('NL123456789B01');
});

test('the design page previews credit notes and downloads a pdf of the unsaved design', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)->test(Design::class)
        ->set('previewDocument', 'credit-note')
        ->assertSee(__('invoices.preview.credit_reason'), false)
        ->call('selectTemplate', 'angle')
        ->call('downloadPreviewPdf')
        ->assertFileDownloaded('invoice-design-preview.pdf');
});

test('invalid design input is rejected and nothing is saved', function () {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)->test(Design::class)
        ->set('design.accent_color', 'blue')
        ->set('design.contact_email', 'not-an-email')
        ->set('design.template', 'retro-2019')
        ->call('save')
        ->assertHasErrors(['design.accent_color', 'design.contact_email', 'design.template']);

    expect(app(InvoiceDesignRepository::class)->values())->toBe(InvoiceDesign::defaults());
});

test('the invoice design page requires the invoices.design permission', function () {
    $viewer = $this->createStaff([], ['invoices.view']);

    $this->actingAs($viewer)->get(route('admin.invoices.design'))->assertForbidden();
    Livewire::actingAs($viewer)->test(Design::class)->assertForbidden();

    $owner = $this->createStaff();
    $this->actingAs($owner)->get(route('admin.invoices.design'))->assertOk();
});

test('only the invoice design item is active on the design page', function () {
    $staff = $this->createStaff();

    $html = $this->actingAs($staff)->get(route('admin.invoices.design'))->assertOk()->getContent();

    preg_match_all('/<a[^>]*href="[^"]*\/admin\/invoices(\/design)?"[^>]*aria-current="page"/', (string) $html, $active);
    expect($active[0])->toHaveCount(2)
        ->and(implode('', $active[0]))->not->toMatch('/href="[^"]*\/admin\/invoices"[^>]*aria-current/');
});
