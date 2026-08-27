<?php

declare(strict_types=1);

use App\Agovena\Imports\ImportAdapterRegistry;

it('maps supported migration source profiles into canonical customer candidates', function (string $source, array $row): void {
    $candidate = app(ImportAdapterRegistry::class)
        ->for($source, 'customer')
        ->map($row, 2);

    expect($candidate->entity)->toBe('customer')
        ->and($candidate->externalId)->not->toBe('')
        ->and($candidate->payload)->toHaveKeys(['email', 'name']);
})->with([
    'hosting_billing' => ['hosting_billing', ['customer_id' => 'A-1', 'email_address' => 'a@example.test', 'full_name' => 'A']],
    'billing_platform' => ['billing_platform', ['userid' => 'B-1', 'email' => 'b@example.test', 'name' => 'B']],
    'shop_platform' => ['shop_platform', ['customer_id' => 'C-1', 'billing_email' => 'c@example.test', 'billing_name' => 'C']],
    'commerce_platform' => ['commerce_platform', ['id' => 'D-1', 'contact_email' => 'd@example.test', 'display_name' => 'D']],
]);

it('rejects unsupported migration source profiles', function (): void {
    expect(fn () => app(ImportAdapterRegistry::class)->for('unknown', 'customer'))
        ->toThrow(InvalidArgumentException::class);
});
