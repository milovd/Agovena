<?php

declare(strict_types=1);

namespace App\Agovena\Payments;

final readonly class HealthResult
{
    public function __construct(
        public bool $ok,
        public string $message = '',
    ) {}

    public static function ok(string $message = 'OK'): self
    {
        return new self(true, $message);
    }

    public static function fail(string $message): self
    {
        return new self(false, $message);
    }
}
