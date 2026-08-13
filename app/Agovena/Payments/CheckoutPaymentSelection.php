<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

/**
 * Parses a checkout option id into a gateway id and optional provider method code.
 * Option ids are either the gateway id (`manual`) or `gateway:method` (`acme:wallet`).
 */
final readonly class CheckoutPaymentSelection
{
    public function __construct(
        public string $optionId,
        public string $gatewayId,
        public ?string $method = null,
    ) {}

    public static function parse(string $optionId): self
    {
        $optionId = trim($optionId);
        $parts = explode(':', $optionId, 2);
        $gatewayId = $parts[0];
        $method = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;

        return new self($optionId, $gatewayId, $method);
    }
}
