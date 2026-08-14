<?php

declare(strict_types=1);

namespace App\Agovena\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class ConfirmsRecentPassword
{
    public const SESSION_KEY = 'auth.password_confirmed_at';

    public function timeoutSeconds(): int
    {
        return max(60, (int) config('agovena.security.password_timeout', 900));
    }

    public function confirmed(): bool
    {
        $confirmedAt = (int) session(self::SESSION_KEY, 0);

        return $confirmedAt > 0 && (time() - $confirmedAt) < $this->timeoutSeconds();
    }

    public function confirm(User $user, string $password): bool
    {
        if ($password === '' || ! Hash::check($password, (string) $user->getAuthPassword())) {
            return false;
        }

        session([self::SESSION_KEY => time()]);

        return true;
    }
}
