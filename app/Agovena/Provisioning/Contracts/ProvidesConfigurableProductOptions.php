<?php

declare(strict_types=1);

namespace App\Agovena\Provisioning\Contracts;

use App\Agovena\Catalog\Options\ConfigurableProductOptionContext;
use App\Agovena\Catalog\Options\ConfigurableProductOptionDefinition;

interface ProvidesConfigurableProductOptions
{
    /** @return list<ConfigurableProductOptionDefinition> */
    public function configurableProductOptions(): array;

    /**
     * Null means the field does not have extension-provided choices.
     * An empty list means the live source returned no available choices.
     *
     * @return list<array{value: string, label: string, price_adjustment_amount?: int}>|null
     */
    public function configurableProductOptionChoices(
        ConfigurableProductOptionDefinition $definition,
        ConfigurableProductOptionContext $context,
    ): ?array;

    /**
     * Null means no provider-specific validation is required for this value.
     */
    public function validateConfigurableProductOptionValue(
        ConfigurableProductOptionDefinition $definition,
        mixed $value,
        ConfigurableProductOptionContext $context,
    ): ?bool;

    public function configurableProductOptionHint(
        ConfigurableProductOptionDefinition $definition,
        ConfigurableProductOptionContext $context,
    ): ?string;
}
