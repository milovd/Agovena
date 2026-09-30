<?php

declare(strict_types=1);

namespace App\Agovena\Cart;

use Illuminate\Support\Facades\Crypt;
use Throwable;

final class CartSelectionCipher
{
    /** @param array<string, mixed> $selections */
    public function encrypt(array $selections): string
    {
        return Crypt::encryptString(json_encode($selections, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed>|null */
    public function decrypt(mixed $encrypted): ?array
    {
        if (! is_string($encrypted) || $encrypted === '') {
            return null;
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
