<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function invoiceForAdminVisualTest(): Invoice
{
    $invoice = Invoice::query()->create([
        'number' => 'INV-VISUAL-01',
        'status' => 'issued',
        'customer_name' => 'Visual Customer',
        'customer_email' => 'visual@example.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1299,
        'tax_amount' => 0,
        'total_amount' => 1299,
        'currency' => 'EUR',
    ]);

    InvoiceItem::query()->create([
        'invoice_id' => $invoice->id,
        'kind' => 'product',
        'label' => 'Domain Registration',
        'quantity' => 1,
        'unit_amount' => 1299,
        'line_total_amount' => 1299,
        'currency' => 'EUR',
    ]);

    return $invoice;
}

it('keeps invoice list filters and permitted row actions in a focusable compact list', function (): void {
    $staff = $this->createStaff([], ['invoices.view', 'invoices.update', 'invoices.delete']);
    $invoice = invoiceForAdminVisualTest();

    $this->actingAs($staff)->get(route('admin.invoices.index'))
        ->assertOk()
        ->assertSee('admin-page--invoices', false)
        ->assertSee('wire:model.live.debounce.300ms="search"', false)
        ->assertSee('wire:model.live="status"', false)
        ->assertSee('role="region"', false)
        ->assertSee('tabindex="0"', false)
        ->assertSee('ag-table__mobile-meta', false)
        ->assertSee($invoice->number)
        ->assertSee('visual@example.test')
        ->assertSee(route('admin.invoices.show', $invoice), false)
        ->assertSee(route('admin.invoices.edit', $invoice), false)
        ->assertSee(route('admin.invoices.pdf', $invoice), false)
        ->assertSee('confirmDelete('.$invoice->id.')', false);
});

it('keeps invoice list management controls permission-gated', function (): void {
    $staff = $this->createStaff([], ['invoices.view']);
    $invoice = invoiceForAdminVisualTest();

    $this->actingAs($staff)->get(route('admin.invoices.index'))
        ->assertOk()
        ->assertSee(route('admin.invoices.show', $invoice), false)
        ->assertSee(route('admin.invoices.pdf', $invoice), false)
        ->assertDontSee(route('admin.invoices.edit', $invoice), false)
        ->assertDontSee('confirmDelete('.$invoice->id.')', false);
});

it('keeps invoice snapshot and document actions visible in the detail hierarchy', function (): void {
    $staff = $this->createStaff([], ['invoices.view', 'invoices.update', 'invoices.delete', 'invoices.void']);
    $invoice = invoiceForAdminVisualTest();

    $this->actingAs($staff)->get(route('admin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('admin-invoice__summary', false)
        ->assertSee('admin-invoice__documents', false)
        ->assertSee('admin-invoice__items', false)
        ->assertSee('admin-invoice__details', false)
        ->assertSee('admin-invoice__financial-actions', false)
        ->assertSee('Domain Registration')
        ->assertSee('Visual Customer')
        ->assertSee(__('admin.invoices.order_none'))
        ->assertSee(route('admin.invoices.edit', $invoice), false)
        ->assertSee(route('admin.invoices.print', $invoice), false)
        ->assertSee(route('admin.invoices.pdf', $invoice), false)
        ->assertSee('wire:click="startDelete"', false)
        ->assertSee('wire:click="startVoid"', false);
});
