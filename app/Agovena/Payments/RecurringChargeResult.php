<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Models\PaymentAttempt;

final readonly class RecurringChargeResult
{
    public function __construct(
        public RecurringChargeOutcome $outcome,
        public ?PaymentAttempt $attempt = null,
        public ?string $message = null,
        public bool $authorizationMissing = false,
    ) {}

    public static function charged(?PaymentAttempt $attempt = null): self
    {
        return new self(RecurringChargeOutcome::Charged, $attempt);
    }

    public static function pending(?PaymentAttempt $attempt = null): self
    {
        return new self(RecurringChargeOutcome::Pending, $attempt);
    }

    public static function skipped(?string $message = null, bool $authorizationMissing = false): self
    {
        return new self(RecurringChargeOutcome::Skipped, message: $message, authorizationMissing: $authorizationMissing);
    }

    public static function failed(?PaymentAttempt $attempt = null, ?string $message = null, bool $authorizationMissing = false): self
    {
        return new self(RecurringChargeOutcome::Failed, $attempt, $message, $authorizationMissing);
    }

    public function paidOrPending(): bool
    {
        return in_array($this->outcome, [RecurringChargeOutcome::Charged, RecurringChargeOutcome::Pending], true);
    }
}
