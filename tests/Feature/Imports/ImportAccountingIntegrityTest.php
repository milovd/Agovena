<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Models\Invoice;

function writeAccountingImportFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-accounting-');
    file_put_contents($path, $contents);

    return $path;
}

/** @param list<string> $headers @param list<string|int> $row */
function writeAccountingCsvFixture(array $headers, array $row): string
{
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $headers);
    fputcsv($handle, $row);
    rewind($handle);
    $contents = stream_get_contents($handle);
    fclose($handle);

    return writeAccountingImportFixture($contents ?: '');
}

it('rejects an imported invoice when subtotal differs from its item lines', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeAccountingImportFixture("external_id,email,name\nC-INVOICE-MISMATCH,mismatch@example.test,Invoice Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $invoicePath = writeAccountingCsvFixture(
        ['external_id', 'customer_external_id', 'number', 'status', 'subtotal_amount', 'total_amount', 'currency', 'items_json'],
        ['I-MISMATCH', 'C-INVOICE-MISMATCH', 'INV-MISMATCH', 'issued', 999, 999, 'EUR', json_encode([['kind' => 'product', 'label' => 'Product', 'quantity' => 1, 'unit_amount' => 1000, 'line_total_amount' => 1000]], JSON_THROW_ON_ERROR)],
    );

    $run = $executor->run($invoicePath, $registry->for('csv', 'invoice'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(Invoice::query()->where('number', 'INV-MISMATCH')->exists())->toBeFalse();

    unlink($customerPath);
    unlink($invoicePath);
});

it('rejects imported invoice adjustments that exceed the line subtotal', function (): void {
    $executor = app(ImportExecutor::class);
    $registry = app(ImportAdapterRegistry::class);
    $customerPath = writeAccountingImportFixture("external_id,email,name\nC-INVOICE-ADJUST,mismatch-adjust@example.test,Invoice Customer\n");
    $executor->run($customerPath, $registry->for('csv', 'customer'), 'csv');
    $invoicePath = writeAccountingCsvFixture(
        ['external_id', 'customer_external_id', 'number', 'status', 'discount_amount', 'total_amount', 'currency', 'items_json'],
        ['I-ADJUST', 'C-INVOICE-ADJUST', 'INV-ADJUST', 'issued', 1001, 0, 'EUR', json_encode([['kind' => 'product', 'label' => 'Product', 'quantity' => 1, 'unit_amount' => 1000, 'line_total_amount' => 1000]], JSON_THROW_ON_ERROR)],
    );

    $run = $executor->run($invoicePath, $registry->for('csv', 'invoice'), 'csv');

    expect($run->errors)->toBe(1)
        ->and(Invoice::query()->where('number', 'INV-ADJUST')->exists())->toBeFalse();

    unlink($customerPath);
    unlink($invoicePath);
});
