<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Models\ImportRow;
use App\Models\ImportRun;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

function writeImportIsolationFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-isolation-');
    file_put_contents($path, $contents);

    return $path;
}

it('rejects a dependency reference from another import source', function (): void {
    $customerUser = User::factory()->create(['email' => 'other-source@example.test']);
    $product = Product::factory()->create(['status' => 'active', 'price_amount' => 2500, 'currency' => 'EUR']);
    $run = ImportRun::query()->create([
        'source' => 'other',
        'entity' => 'customer',
        'mode' => 'live',
        'filename' => 'other.csv',
        'status' => 'completed',
        'total_rows' => 1,
        'imported_rows' => 1,
        'duplicate_rows' => 0,
        'error_rows' => 0,
    ]);
    $customerRow = ImportRow::query()->create([
        'import_run_id' => $run->id,
        'line' => 2,
        'entity' => 'customer',
        'external_id' => 'csv:C-1',
        'status' => 'imported',
        'imported_model_type' => User::class,
        'imported_model_id' => $customerUser->id,
    ]);
    ImportRow::query()->create([
        'import_run_id' => $run->id,
        'line' => 3,
        'entity' => 'product',
        'external_id' => 'csv:P-1',
        'status' => 'imported',
        'imported_model_type' => Product::class,
        'imported_model_id' => $product->id,
    ]);

    $path = writeImportIsolationFixture(implode("\n", [
        'external_id,customer_external_id,total_amount,currency,items_json',
        'O-1,C-1,2500,EUR,"[{""product_external_id"":""P-1"",""quantity"":1,""unit_amount"":2500}]"',
    ]));

    $result = app(ImportExecutor::class)->run(
        $path,
        app(ImportAdapterRegistry::class)->for('csv', 'order'),
        'csv',
    );

    unlink($path);

    expect($result->errors)->toBe(1)
        ->and(Order::query()->where('number', 'O-1')->exists())->toBeFalse()
        ->and($customerRow->fresh()->status)->toBe('imported');
});

it('rejects an import source that does not match the adapter namespace', function (): void {
    $path = writeImportIsolationFixture("external_id,name,price,currency\nP-FOREIGN,Foreign source,2500,EUR\n");
    $runsBefore = ImportRun::query()->count();

    expect(fn () => app(ImportExecutor::class)->run(
        $path,
        app(ImportAdapterRegistry::class)->for('csv', 'product'),
        'other',
    ))->toThrow(InvalidArgumentException::class);

    unlink($path);

    expect(ImportRun::query()->count())->toBe($runsBefore);
});

it('rejects a product with an invalid currency code', function (): void {
    $path = writeImportIsolationFixture("external_id,name,price,currency\nP-INVALID,Invalid currency,2500,EURO\n");

    $result = app(ImportExecutor::class)->run(
        $path,
        app(ImportAdapterRegistry::class)->for('csv', 'product'),
        'csv',
    );

    unlink($path);

    expect($result->errors)->toBe(1)
        ->and(Product::query()->where('slug', 'invalid-currency')->exists())->toBeFalse();
});
