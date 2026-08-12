<?php

use App\Agovena\Installation\InstallationState;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

pest()->beforeEach(function (): void {
    if (! $this->app->runningUnitTests()) {
        return;
    }

    // Feature tests assume a completed install unless reset in installer-focused suites.
    $state = app(InstallationState::class);
    if ($state->notInstalled()) {
        $state->markInstalled();
    }
})->in('Feature');
