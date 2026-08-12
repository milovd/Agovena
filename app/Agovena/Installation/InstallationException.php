<?php

declare(strict_types=1);

namespace App\Agovena\Installation;

use RuntimeException;

final class InstallationException extends RuntimeException
{
    public static function alreadyInstalled(): self
    {
        return new self('Agovena is already installed.');
    }

    public static function ownerAlreadyExists(): self
    {
        return new self('An owner account already exists. Finish or recover the interrupted installation carefully.');
    }

    public static function requirementsNotMet(string $detail): self
    {
        return new self('Installation requirements are not met: '.$detail);
    }

    public static function invalidCurrency(string $code): self
    {
        return new self("Currency [{$code}] is not available.");
    }

    public static function invalidTheme(string $id): self
    {
        return new self("Theme [{$id}] is not installed.");
    }

    public static function invalidLocale(string $locale): self
    {
        return new self("Locale [{$locale}] is not configured.");
    }
}
