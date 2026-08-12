<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

use App\Agovena\Payments\Contracts\PaymentGateway;

/**
 * Collects PaymentGateway implementations from Core and enabled Extensions.
 */
final class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->id()] = $gateway;
    }

    public function get(string $id): ?PaymentGateway
    {
        return $this->gateways[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->gateways[$id]);
    }

    /**
     * @return list<PaymentGateway>
     */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->gateways);
    }

    public function clear(): void
    {
        $this->gateways = [];
    }
}
