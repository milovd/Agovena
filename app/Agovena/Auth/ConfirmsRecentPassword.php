<?php

declare(strict_types=1);

namespace App\Agovena\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class ConfirmsRecentPassword
{
    public const SESSION_KEY = 'auth.password_confirmed_at';

    public const SESSION_USER_KEY = 'auth.password_confirmed_user_id';

    public function timeoutSeconds(): int
    {
        return max(60, (int) config('agovena.security.password_timeout', 900));
    }

    public function confirmed(): bool
    {
        $userId = Auth::id();
        $confirmedAt = (int) session(self::SESSION_KEY, 0);
        $confirmedUserId = (int) session(self::SESSION_USER_KEY, 0);

        return $userId !== null
            && $confirmedUserId === (int) $userId
            && $confirmedAt > 0
            && (time() - $confirmedAt) < $this->timeoutSeconds();
    }

    public function confirm(User $user, string $password): bool
    {
        if (! Auth::check() || (string) Auth::id() !== (string) $user->getAuthIdentifier()) {
            return false;
        }

        if ($password === '' || ! Hash::check($password, (string) $user->getAuthPassword())) {
            return false;
        }

        session([
            self::SESSION_KEY => time(),
            self::SESSION_USER_KEY => $user->getAuthIdentifier(),
        ]);

        return true;
    }

    public function forget(): void
    {
        session()->forget([
            self::SESSION_KEY,
            self::SESSION_USER_KEY,
        ]);
    }
}
