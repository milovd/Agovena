<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

/**
 * Optional gateway capability for provider-discovered checkout method settings.
 */
interface ConfiguresCheckoutMethods
{
    /**
     * @return list<array{id: string, label: string, icon: ?string}>
     */
    public function configurableCheckoutMethods(): array;
}
