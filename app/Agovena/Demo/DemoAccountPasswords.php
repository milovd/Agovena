<?php

declare(strict_types=1);

namespace App\Agovena\Demo;

final readonly class DemoAccountPasswords
{
    public function __construct(
        public string $customer,
        public string $admin,
    ) {}

    public static function generate(): self
    {
        return new self(
            bin2hex(random_bytes(32)),
            bin2hex(random_bytes(32)),
        );
    }
}
