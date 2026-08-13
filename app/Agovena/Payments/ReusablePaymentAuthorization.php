<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use DateTimeInterface;

/**
 * Provider-neutral view of whether a customer can be charged off-session.
 * No card data. No provider mandate/customer identifiers.
 */
final readonly class ReusablePaymentAuthorization
{
    public function __construct(
        public string $gatewayId,
        public bool $available,
        public string $status,
        public ?DateTimeInterface $lastVerifiedAt = null,
    ) {}

    public static function missing(string $gatewayId): self
    {
        return new self($gatewayId, false, 'missing');
    }

    public static function active(string $gatewayId, ?DateTimeInterface $lastVerifiedAt = null): self
    {
        return new self($gatewayId, true, 'active', $lastVerifiedAt);
    }

    public static function revoked(string $gatewayId, ?DateTimeInterface $lastVerifiedAt = null): self
    {
        return new self($gatewayId, false, 'revoked', $lastVerifiedAt);
    }
}
