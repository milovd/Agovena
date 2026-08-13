<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Refund;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Money movement completed. Modules may listen and apply explicit policy.
 * Core does not revoke entitlements, cancel subscriptions, terminate services, or restock.
 */
final class RefundRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Refund $refund) {}
}
