<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

final readonly class OAuthCallbackResult
{
    public function __construct(
        public string $redirect,
        public int $userId,
        public bool $linked,
    ) {}
}
