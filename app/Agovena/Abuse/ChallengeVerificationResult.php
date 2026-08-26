<?php

declare(strict_types=1);

namespace App\Agovena\Abuse;

final readonly class ChallengeVerificationResult
{
    public function __construct(
        public bool $accepted,
        public string $provider,
        public string $reason,
    ) {}
}
