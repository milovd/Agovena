<?php

declare(strict_types=1);

namespace App\Agovena\Checkout\AddressAutocomplete;

final readonly class AddressSuggestion
{
    public function __construct(
        public string $id,
        public string $label,
        public ?string $secondary,
        public string $source,
        public ?int $savedAddressId = null,
    ) {}

    /**
     * @return array{id: string, label: string, secondary: ?string, source: string, saved_address_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'secondary' => $this->secondary,
            'source' => $this->source,
            'saved_address_id' => $this->savedAddressId,
        ];
    }

    /**
     * @param  array{id: string, label: string, secondary?: ?string, source: string, saved_address_id?: ?int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            label: $data['label'],
            secondary: $data['secondary'] ?? null,
            source: $data['source'],
            savedAddressId: $data['saved_address_id'] ?? null,
        );
    }
}
