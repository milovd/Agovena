<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

final readonly class OAuthProviderDefinition
{
    /** @param list<string> $scopes */
    public function __construct(
        public string $id,
        public string $authorizationEndpoint,
        public array $scopes,
    ) {}
}
