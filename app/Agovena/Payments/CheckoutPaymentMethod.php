<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

/**
 * Checkout-facing payment option. Gateways may expose multiple methods
 * (for example card vs wallet) without those methods becoming Extensions.
 */
final readonly class CheckoutPaymentMethod
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $gatewayId,
        public string $id,
        public string $label,
        public ?string $icon = null,
        public array $metadata = [],
    ) {}

    /**
     * @return array{id: string, gateway_id: string, label: string, icon: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gateway_id' => $this->gatewayId,
            'label' => $this->label,
            'icon' => $this->icon,
        ];
    }
}
