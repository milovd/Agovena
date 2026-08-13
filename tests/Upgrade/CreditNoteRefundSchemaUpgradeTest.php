<?php

declare(strict_types=1);

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('credit note and refund tables can be applied onto existing invoices and payments', function () {
    Artisan::call('migrate');

    $order = Order::factory()->create([
        'customer_email' => 'upgrade-finance@example.test',
        'customer_name' => 'Existing Buyer',
        'status' => 'paid',
        'total_amount' => 2500,
        'subtotal_amount' => 2500,
        'currency' => 'EUR',
    ]);

    $invoice = Invoice::query()->create([
        'number' => 'INV-UPGRADE-FIN-00001',
        'status' => 'paid',
        'order_id' => $order->id,
        'customer_name' => $order->customer_name,
        'customer_email' => $order->customer_email,
        'issued_at' => now()->toDateString(),
        'subtotal_amount' => 2500,
        'tax_amount' => 0,
        'total_amount' => 2500,
        'currency' => 'EUR',
        'paid_at' => now(),
    ]);

    $payment = Payment::factory()->create([
        'order_id' => $order->id,
        'amount' => 2500,
        'currency' => 'EUR',
        'status' => 'paid',
        'paid_at' => now(),
    ]);

    expect(Schema::hasTable('credit_notes'))->toBeTrue()
        ->and(Schema::hasTable('refunds'))->toBeTrue();

    Schema::dropIfExists('refunds');
    Schema::dropIfExists('credit_note_items');
    Schema::dropIfExists('credit_notes');
    DB::table('migrations')->where('migration', '2026_08_13_130000_create_credit_notes_and_refunds_tables')->delete();

    expect(Schema::hasTable('credit_notes'))->toBeFalse()
        ->and(Invoice::query()->whereKey($invoice->id)->value('total_amount'))->toBe(2500)
        ->and(Payment::query()->whereKey($payment->id)->value('amount'))->toBe(2500);

    Artisan::call('migrate');

    expect(Schema::hasTable('credit_notes'))->toBeTrue()
        ->and(Schema::hasTable('credit_note_items'))->toBeTrue()
        ->and(Schema::hasTable('refunds'))->toBeTrue()
        ->and(Invoice::query()->whereKey($invoice->id)->value('customer_email'))->toBe('upgrade-finance@example.test')
        ->and(Invoice::query()->whereKey($invoice->id)->value('total_amount'))->toBe(2500)
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue();
});
