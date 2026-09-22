<?php

declare(strict_types=1);

namespace App\Agovena\Subscriptions;

use Carbon\CarbonImmutable;

/**
 * Core recurring renewal processor.
 */
interface ProcessesSubscriptionRenewals
{
    public function processDue(?CarbonImmutable $now = null): int;
}
