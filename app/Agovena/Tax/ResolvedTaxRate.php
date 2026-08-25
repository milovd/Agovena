<?php

declare(strict_types=1);

namespace App\Agovena\Tax;

use App\Models\TaxRate;

final readonly class ResolvedTaxRate
{
    public function __construct(
        public string $name,
        public int $rateBps,
        public bool $appliesToShipping,
        public TaxRateSource $source,
        public ?string $country = null,
        public ?int $taxRateId = null,
    ) {}

    public static function none(): self
    {
        return new self(
            name: '',
            rateBps: 0,
            appliesToShipping: false,
            source: TaxRateSource::None,
        );
    }

    public static function fromOverride(TaxRate $rate): self
    {
        return new self(
            name: $rate->name,
            rateBps: $rate->rate_bps,
            appliesToShipping: $rate->applies_to_shipping,
            source: TaxRateSource::Override,
            country: $rate->country !== null ? strtoupper($rate->country) : null,
            taxRateId: $rate->id,
        );
    }

    public static function fromDisabled(TaxRate $rate): self
    {
        return new self(
            name: $rate->name,
            rateBps: 0,
            appliesToShipping: false,
            source: TaxRateSource::Disabled,
            country: $rate->country !== null ? strtoupper($rate->country) : null,
            taxRateId: $rate->id,
        );
    }

    public static function fromAutomatic(string $country, string $name, int $rateBps): self
    {
        return new self(
            name: $name,
            rateBps: $rateBps,
            appliesToShipping: false,
            source: TaxRateSource::Automatic,
            country: strtoupper($country),
        );
    }

    public static function fromFallback(TaxRate $rate): self
    {
        return new self(
            name: $rate->name,
            rateBps: $rate->rate_bps,
            appliesToShipping: $rate->applies_to_shipping,
            source: TaxRateSource::Fallback,
            country: null,
            taxRateId: $rate->id,
        );
    }

    public function applies(): bool
    {
        return $this->source !== TaxRateSource::None
            && $this->source !== TaxRateSource::Disabled;
    }
}
