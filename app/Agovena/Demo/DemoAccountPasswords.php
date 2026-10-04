<?php

declare(strict_types=1);

namespace App\Agovena\Demo;

use Illuminate\Support\Str;

final readonly class DemoAccountPasswords
{
    public function __construct(
        public string $customer,
        public string $admin,
    ) {}

    public static function generate(): self
    {
        return new self(
            Str::password(24, letters: true, numbers: true, symbols: false),
            Str::password(24, letters: true, numbers: true, symbols: false),
        );
    }
}
