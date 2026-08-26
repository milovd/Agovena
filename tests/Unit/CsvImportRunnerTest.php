<?php

declare(strict_types=1);

use App\Agovena\Imports\Contracts\ImportAdapter;
use App\Agovena\Imports\CsvImportRunner;
use App\Agovena\Imports\ImportCandidate;

final class TestCustomerImportAdapter implements ImportAdapter
{
    public function map(array $row, int $line): ImportCandidate
    {
        $email = strtolower(trim((string) ($row['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required.');
        }

        return new ImportCandidate(
            entity: 'customer',
            externalId: trim((string) ($row['external_id'] ?? '')),
            payload: ['email' => $email, 'name' => trim((string) ($row['name'] ?? ''))],
        );
    }
}

it('previews mapped csv rows and reports duplicates without writing data', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-');
    file_put_contents($path, "external_id,email,name\nC-1,First@Example.test,First\nC-1,first@example.test,First\nC-2,second@example.test,Second\n");

    $report = app(CsvImportRunner::class)->preview($path, new TestCustomerImportAdapter);

    expect($report->dryRun)->toBeTrue()
        ->and($report->read)->toBe(3)
        ->and($report->valid)->toBe(2)
        ->and($report->duplicates)->toBe(1)
        ->and($report->errors)->toBe(0)
        ->and($report->candidates)->toHaveCount(2);

    @unlink($path);
});

it('reports malformed rows without aborting the csv preview', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'agovena-import-');
    file_put_contents($path, "external_id,email,name\nC-1,invalid,First\nC-2,valid@example.test,Second\n");

    $report = app(CsvImportRunner::class)->preview($path, new TestCustomerImportAdapter);

    expect($report->read)->toBe(2)
        ->and($report->valid)->toBe(1)
        ->and($report->errors)->toBe(1)
        ->and($report->rowErrors[2])->toContain('A valid email is required.');

    @unlink($path);
});
