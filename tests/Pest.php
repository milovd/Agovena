<?php

use App\Agovena\Installation\InstallationState;
use Tests\MultiProcessTestCase;
use Tests\TestCase;
use Tests\UpgradeTestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Concurrency', 'Performance');
pest()->extend(MultiProcessTestCase::class)->in('MultiProcess');
pest()->extend(UpgradeTestCase::class)->in('Upgrade');

pest()->beforeEach(function (): void {
    if (! $this->app->runningUnitTests()) {
        return;
    }

    // Feature tests assume a completed install unless reset in installer-focused suites.
    $state = app(InstallationState::class);
    if ($state->notInstalled()) {
        $state->markInstalled();
    }
})->in('Feature', 'Concurrency', 'Performance', 'MultiProcess');

pest()->beforeEach(function (): void {
    if (config('database.default') !== 'mysql') {
        test()->markTestSkipped('MariaDB concurrency suite');
    }
})->in('Concurrency', 'MultiProcess');
