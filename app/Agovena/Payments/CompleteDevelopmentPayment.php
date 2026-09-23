<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

/**
 * Development-only instant payment completion. Never a production gateway.
 */
final class CompleteDevelopmentPayment
{
    public function __construct(
        private readonly CompleteDirectPayment $completeDirectPayment,
    ) {}

    public function handle(Order $order, bool $lifecycleLockHeld = false): Payment
    {
        if (! (bool) config('agovena.payments.allow_development_instant_pay')) {
            throw ValidationException::withMessages([
                'payment' => 'Development instant payment is not enabled.',
            ]);
        }

        if (app()->environment('production')) {
            throw ValidationException::withMessages([
                'payment' => 'Development instant payment is unavailable in production.',
            ]);
        }

        return $this->completeDirectPayment->handle(
            $order,
            'development',
            reference: 'dev-instant',
            requiredPaymentMethod: 'development',
            lifecycleLockHeld: $lifecycleLockHeld,
        );
    }
}
