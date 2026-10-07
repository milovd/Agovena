<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;
use App\Agovena\Imports\ImportExecutor;
use App\Agovena\Imports\ImportRollback;
use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function writeImportIdempotencyFixture(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-idempotency-');
    file_put_contents($path, $contents);

    return $path;
}

it('treats a reserved source identity as a deterministic duplicate', function (): void {
    $reservedRun = ImportRun::query()->create([
        'source' => 'csv',
        'entity' => 'customer',
        'mode' => 'execute',
        'status' => 'running',
        'read' => 1,
        'valid' => 1,
        'started_at' => now(),
    ]);
    $reservedRow = $reservedRun->rows()->create([
        'line' => 2,
        'entity' => 'customer',
        'external_id' => 'csv:C-RESERVED',
        'status' => 'pending',
    ]);
    DB::table('import_identity_reservations')->insert([
        'source' => 'csv',
        'entity' => 'customer',
        'external_id' => 'csv:C-RESERVED',
        'import_run_id' => $reservedRun->id,
        'import_row_id' => $reservedRow->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $path = writeImportIdempotencyFixture("external_id,email,name\nC-RESERVED,new@example.test,New Customer\n");
    $run = app(ImportExecutor::class)->run($path, app(ImportAdapterRegistry::class)->for('csv', 'customer'), 'csv');

    expect($run->duplicates)->toBe(1)
        ->and($run->rows()->where('status', 'duplicate')->count())->toBe(1)
        ->and(User::query()->where('email', 'new@example.test')->exists())->toBeFalse();

    unlink($path);
});

it('releases an imported identity reservation after safe rollback', function (): void {
    $path = writeImportIdempotencyFixture("external_id,email,name\nC-ROLLBACK-RESERVED,rollback-reserved@example.test,Rollback Customer\n");
    $run = app(ImportExecutor::class)->run($path, app(ImportAdapterRegistry::class)->for('csv', 'customer'), 'csv');

    expect(DB::table('import_identity_reservations')
        ->where('source', 'csv')
        ->where('entity', 'customer')
        ->where('external_id', 'csv:C-ROLLBACK-RESERVED')
        ->exists())->toBeTrue();

    app(ImportRollback::class)->handle($run);

    expect(DB::table('import_identity_reservations')
        ->where('source', 'csv')
        ->where('entity', 'customer')
        ->where('external_id', 'csv:C-ROLLBACK-RESERVED')
        ->exists())->toBeFalse();

    unlink($path);
});
