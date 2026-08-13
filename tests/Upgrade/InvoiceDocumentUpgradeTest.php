<?php

declare(strict_types=1);

use App\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('invoice document snapshot columns can be applied onto existing invoices', function () {
    Artisan::call('migrate');

    $invoiceId = Invoice::query()->create([
        'number' => 'INV-UPGRADE-00001',
        'status' => 'paid',
        'customer_name' => 'Existing',
        'customer_email' => 'upgrade-invoice@example.test',
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 1000,
        'tax_amount' => 0,
        'total_amount' => 1000,
        'currency' => 'EUR',
    ])->id;

    expect(Schema::hasColumn('invoices', 'paid_at'))->toBeTrue()
        ->and(Schema::hasColumn('invoice_items', 'options_snapshot'))->toBeTrue();

    Schema::table('invoices', function ($table): void {
        $table->dropColumn(['credit_amount', 'tax_rate_name', 'tax_rate_bps', 'paid_at']);
    });
    Schema::table('invoice_items', function ($table): void {
        $table->dropColumn(['kind', 'options_snapshot']);
    });
    DB::table('migrations')->where('migration', '2026_08_13_110000_add_invoice_document_snapshots')->delete();

    expect(Schema::hasColumn('invoices', 'paid_at'))->toBeFalse()
        ->and(Invoice::query()->whereKey($invoiceId)->exists())->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasColumn('invoices', 'credit_amount'))->toBeTrue()
        ->and(Schema::hasColumn('invoices', 'paid_at'))->toBeTrue()
        ->and(Schema::hasColumn('invoice_items', 'kind'))->toBeTrue()
        ->and(Schema::hasColumn('invoice_items', 'options_snapshot'))->toBeTrue()
        ->and(Invoice::query()->whereKey($invoiceId)->value('customer_email'))->toBe('upgrade-invoice@example.test');
});
