<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Agovena\Payments\RecordRefund;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function writePaymentIntegrityFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-payment-');
    file_put_contents($path, $contents);

    return $path;
}

/** @param list<string> $headers @param list<string|int> $row */
function writePaymentIntegrityCsv(array $headers, array $row): string
{
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $headers);
    fputcsv($handle, $row);
    rewind($handle);
    $contents = stream_get_contents($handle);
    fclose($handle);

    return writePaymentIntegrityFixture($contents ?: '');
}

it('rejects an imported paid payment when its amount differs from the order total', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writePaymentIntegrityFixture("external_id,email,name\nC-PAYMENT-MISMATCH,payment-mismatch@example.test,Payment Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writePaymentIntegrityFixture("external_id,name,price_amount\nP-PAYMENT-MISMATCH,Payment product,2500\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $orderPath = writePaymentIntegrityCsv(
        ['external_id', 'customer_external_id', 'total_amount', 'currency', 'items_json'],
        ['O-PAYMENT-MISMATCH', 'C-PAYMENT-MISMATCH', 2500, 'EUR', json_encode([['product_external_id' => 'P-PAYMENT-MISMATCH', 'quantity' => 1, 'unit_amount' => 2500]], JSON_THROW_ON_ERROR)],
    );
    $executor->run($orderPath, $registry->for('csv', 'order'), 'csv');
    $paymentPath = writePaymentIntegrityCsv(
        ['external_id', 'order_external_id', 'amount', 'currency', 'method', 'status'],
        ['T-PAYMENT-MISMATCH', 'O-PAYMENT-MISMATCH', 2499, 'EUR', 'manual', 'paid'],
    );

    $run = $executor->run($paymentPath, $registry->for('csv', 'payment'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(Payment::query()->where('amount', 2499)->exists())->toBeFalse();

    unlink($customerPath);
    unlink($productPath);
    unlink($orderPath);
    unlink($paymentPath);
});

it('rejects an imported payment when its currency differs from the order', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writePaymentIntegrityFixture("external_id,email,name\nC-PAYMENT-CURRENCY,payment-currency@example.test,Payment Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writePaymentIntegrityFixture("external_id,name,price_amount\nP-PAYMENT-CURRENCY,Payment product,2500\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $orderPath = writePaymentIntegrityCsv(
        ['external_id', 'customer_external_id', 'total_amount', 'currency', 'items_json'],
        ['O-PAYMENT-CURRENCY', 'C-PAYMENT-CURRENCY', 2500, 'EUR', json_encode([['product_external_id' => 'P-PAYMENT-CURRENCY', 'quantity' => 1, 'unit_amount' => 2500]], JSON_THROW_ON_ERROR)],
    );
    $executor->run($orderPath, $registry->for('csv', 'order'), 'csv');
    $paymentPath = writePaymentIntegrityCsv(
        ['external_id', 'order_external_id', 'amount', 'currency', 'method', 'status'],
        ['T-PAYMENT-CURRENCY', 'O-PAYMENT-CURRENCY', 2500, 'USD', 'manual', 'paid'],
    );

    $run = $executor->run($paymentPath, $registry->for('csv', 'payment'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(Payment::query()->where('currency', 'USD')->exists())->toBeFalse();

    unlink($customerPath);
    unlink($productPath);
    unlink($orderPath);
    unlink($paymentPath);
});

it('imports the completed refund amount for a partially refunded payment', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writePaymentIntegrityFixture("external_id,email,name\nC-PAYMENT-REFUND,payment-refund@example.test,Payment Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $productPath = writePaymentIntegrityFixture("external_id,name,price_amount\nP-PAYMENT-REFUND,Payment product,2500\n");
    $executor->run($productPath, $registry->for('csv', 'product'), 'csv');
    $orderPath = writePaymentIntegrityCsv(
        ['external_id', 'customer_external_id', 'total_amount', 'currency', 'items_json'],
        ['O-PAYMENT-REFUND', 'C-PAYMENT-REFUND', 2500, 'EUR', json_encode([['product_external_id' => 'P-PAYMENT-REFUND', 'quantity' => 1, 'unit_amount' => 2500]], JSON_THROW_ON_ERROR)],
    );
    $executor->run($orderPath, $registry->for('csv', 'order'), 'csv');
    $paymentPath = writePaymentIntegrityCsv(
        ['external_id', 'order_external_id', 'amount', 'currency', 'method', 'status', 'refunded_amount'],
        ['T-PAYMENT-REFUND', 'O-PAYMENT-REFUND', 2500, 'EUR', 'manual', 'partially_refunded', 1000],
    );

    $run = $executor->run($paymentPath, $registry->for('csv', 'payment'), 'csv');
    $payment = Payment::query()->where('amount', 2500)->firstOrFail();

    expect($run->errors)->toBe(0)
        ->and($payment->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($payment->refundedAmount())->toBe(1000)
        ->and($payment->remainingRefundable())->toBe(1500)
        ->and(Refund::query()->where('payment_id', $payment->id)->sum('amount'))->toBe(1000);

    $staff = $this->createStaff([], ['payments.refund']);
    expect(fn () => app(RecordRefund::class)->handle($payment, $staff, 2500, 'Second full refund'))
        ->toThrow(ValidationException::class);

    unlink($customerPath);
    unlink($productPath);
    unlink($orderPath);
    unlink($paymentPath);
});
