<?php

declare(strict_types=1);

test('payment attempt uniqueness migration fails closed instead of deleting historical duplicates', function (): void {
    $migration = file_get_contents(database_path('migrations/2026_08_14_210000_add_unique_payment_attempt_external_id.php'));

    expect($migration)->toBeString()
        ->and($migration)->toContain('throw new RuntimeException')
        ->and($migration)->not->toContain("->whereIn('id', \$duplicates)->delete()");
});
