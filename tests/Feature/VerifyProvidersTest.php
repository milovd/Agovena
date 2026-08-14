<?php

declare(strict_types=1);

test('provider verification runs health checks without creating remote resources', function () {
    $this->artisan('agovena:verify-providers')
        ->expectsOutputToContain('This only tests connectivity')
        ->assertSuccessful();
});
