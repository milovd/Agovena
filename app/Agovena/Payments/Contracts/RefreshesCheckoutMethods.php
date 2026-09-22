<?php

declare(strict_types=1);

namespace App\Agovena\Payments\Contracts;

interface RefreshesCheckoutMethods
{
    /**
     * @return list<array{id: string, label: string, icon: ?string}>
     */
    public function refreshConfigurableCheckoutMethods(): array;
}
