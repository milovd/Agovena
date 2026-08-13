<?php

declare(strict_types=1);

namespace App\Agovena\Subscriptions;

use Carbon\CarbonImmutable;

/**
 * Optional renewal processor. Bound by the Subscriptions Module when enabled.
 */
interface ProcessesSubscriptionRenewals
{
    public function processDue(?CarbonImmutable $now = null): int;
}
