<?php

declare(strict_types=1);

namespace App\Agovena\Availability;

use App\Agovena\Availability\Contracts\ProvidesCapacity;

final class CapacityProviderRegistry
{
    /** @var array<string, ProvidesCapacity> */
    private array $providers = [];

    public function register(ProvidesCapacity $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function get(string $key): ?ProvidesCapacity
    {
        return $this->providers[$key] ?? null;
    }
}
