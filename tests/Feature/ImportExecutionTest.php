<?php

declare(strict_types=1);

use Agovena\Modules\Subscriptions\Models\Subscription;
use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Agovena\Imports\ImportRollback;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\User;

function writeImportFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-run-');
    file_put_contents($path, $contents);

    return $path;
}

it('keeps preview imports read-only and persists an auditable execution run', function (): void {
    $path = writeImportFixture("external_id,email,name\nC-1,imported@example.test,Imported Customer\n");
    $adapter = app(ImportAdapterRegistry::class)->for('csv', 'customer');
    $executor = app(ImportExecutor::class);

    $preview = $executor->run($path, $adapter, 'csv', dryRun: true);

    expect($preview->status)->toBe('preview')
        ->and(User::query()->where('email', 'imported@example.test')->exists())->toBeFalse();

    $run = $executor->run($path, $adapter, 'csv', dryRun: false);

    expect($run->status)->toBe('completed')
        ->and(User::query()->where('email', 'imported@example.test')->exists())->toBeTrue()
        ->and(ImportRow::query()->where('import_run_id', $run->id)->where('status', 'imported')->count())->toBe(1);

    unlink($path);
});

it('skips a previously imported source identifier and can roll back its own writes', function (): void {
    $path = writeImportFixture("external_id,email,name\nC-2,second@example.test,Second Customer\n");
    $adapter = app(ImportAdapterRegistry::class)->for('csv', 'customer');
    $executor = app(ImportExecutor::class);

    $run = $executor->run($path, $adapter, 'csv', dryRun: false);
    $duplicateRun = $executor->run($path, $adapter, 'csv', dryRun: false);

    expect($duplicateRun->status)->toBe('completed')
        ->and($duplicateRun->duplicates)->toBe(1)
        ->and(User::query()->where('email', 'second@example.test')->count())->toBe(1);

    $rolledBack = app(ImportRollback::class)->handle($run);

    expect($rolledBack->status)->toBe('rolled_back')
        ->and(User::query()->where('email', 'second@example.test')->exists())->toBeFalse()
        ->and(ImportRow::query()->where('import_run_id', $run->id)->where('status', 'rolled_back')->count())->toBe(1);

    unlink($path);
});

it('fails closed when an import row is malformed', function (): void {
    $path = writeImportFixture("external_id,email,name\nC-3,not-an-email,Invalid\n");
    $adapter = app(ImportAdapterRegistry::class)->for('csv', 'customer');

    $run = app(ImportExecutor::class)->run($path, $adapter, 'csv', dryRun: false);

    expect($run->status)->toBe('completed')
        ->and($run->errors)->toBe(1)
        ->and(User::query()->where('email', 'not-an-email')->exists())->toBeFalse();

    unlink($path);
});

it('imports subscriptions only when its optional module is enabled', function (): void {
    installAndEnableModules(['subscriptions']);
    $executor = app(ImportExecutor::class);

    $customerPath = writeImportFixture("external_id,email,name\nC-4,subscription@example.test,Subscription Customer\n");
    $executor->run($customerPath, app(ImportAdapterRegistry::class)->for('csv', 'customer'), 'csv');
    $productPath = writeImportFixture("external_id,name,price_amount\nP-4,Managed plan,2500\n");
    $executor->run($productPath, app(ImportAdapterRegistry::class)->for('csv', 'product'), 'csv');
    $subscriptionPath = writeImportFixture("external_id,customer_external_id,product_external_id,status\nS-4,C-4,P-4,active\n");

    $run = $executor->run($subscriptionPath, app(ImportAdapterRegistry::class)->for('csv', 'subscription'), 'csv');

    expect($run->errors)->toBe(0)
        ->and(Subscription::query()->count())->toBe(1)
        ->and(Subscription::query()->first()->status->value)->toBe('active');

    unlink($customerPath);
    unlink($productPath);
    unlink($subscriptionPath);
});

it('executes a validated product import into draft products', function (): void {
    $path = writeImportFixture("external_id,name,price_amount\nP-1,Starter plan,1999\n");
    $adapter = app(ImportAdapterRegistry::class)->for('csv', 'product');

    $run = app(ImportExecutor::class)->run($path, $adapter, 'csv', dryRun: false);

    expect($run->status)->toBe('completed')
        ->and(Product::query()->where('name', 'Starter plan')->value('price_amount'))->toBe(1999)
        ->and(ImportRow::query()->where('import_run_id', $run->id)->value('status'))->toBe('imported');

    unlink($path);
});
