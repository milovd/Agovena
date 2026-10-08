<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

/**
 * Whether an enabled Extension has every required setting filled. Carries keys and
 * translated labels of the missing settings, never their values.
 */
final readonly class ExtensionConfiguration
{
    /**
     * @param  list<string>  $missingKeys
     * @param  list<string>  $missingLabels
     */
    public function __construct(
        public string $extensionId,
        public string $name,
        public array $missingKeys = [],
        public array $missingLabels = [],
    ) {}

    public function configured(): bool
    {
        return $this->missingKeys === [];
    }
}
