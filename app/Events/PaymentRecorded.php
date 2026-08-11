<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PaymentRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public Payment $payment) {}
}
