<?php

declare(strict_types=1);

namespace App\Agovena\Auth;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Throwable;

final class TotpTwoFactor
{
    public const SESSION_SETUP_SECRET = 'auth.two_factor.setup_secret';

    public const SESSION_PENDING_ID = 'auth.two_factor.id';

    public const SESSION_PENDING_REMEMBER = 'auth.two_factor.remember';

    public const SESSION_PENDING_INTENDED = 'auth.two_factor.intended';

    public const SESSION_PRIVILEGED_AT_LOGIN = 'auth.privileged_at_login';

    public const SESSION_VERIFIED_USER = 'auth.two_factor.verified_user_id';

    /** Number of 30-second steps accepted on either side of now. */
    private const VERIFY_WINDOW = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function qrSvg(string $holder, string $secret): string
    {
        $company = (string) config('app.name', 'Agovena');
        $url = $this->google2fa->getQRCodeUrl($company, $holder, $secret);
        $renderer = new ImageRenderer(new RendererStyle(192), new SvgImageBackEnd);
        $writer = new Writer($renderer);

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($secret === '' || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        try {
            $verified = $this->google2fa->verifyKey($secret, $code, self::VERIFY_WINDOW);

            return $verified === true || (is_int($verified) && $verified > 0);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(Str::random(4).'-'.Str::random(4));
        }

        return $codes;
    }

    /**
     * @param  list<string>  $plain
     * @return list<string>
     */
    public function hashRecoveryCodes(array $plain): array
    {
        return array_map(static fn (string $code): string => Hash::make($code), $plain);
    }

    /**
     * @param  list<string>|null  $hashed
     * @return list<string>|null Remaining hashes, or null when the code is invalid.
     */
    public function consumeRecoveryCode(?array $hashed, string $plain): ?array
    {
        $plain = strtoupper(trim($plain));
        if ($hashed === null || $hashed === [] || $plain === '') {
            return null;
        }

        foreach ($hashed as $index => $hash) {
            if ($hash === '') {
                continue;
            }
            if (! Hash::check($plain, $hash)) {
                continue;
            }
            unset($hashed[$index]);

            return array_values($hashed);
        }

        return null;
    }

    public function enable(User $user, string $secret, array $hashedRecoveryCodes): void
    {
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashedRecoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }

    public function markVerified(User $user): void
    {
        session([
            self::SESSION_VERIFIED_USER => $user->id,
            self::SESSION_PRIVILEGED_AT_LOGIN => $user->canAccessAdmin(),
        ]);
    }

    public function isVerified(User $user): bool
    {
        return (int) session(self::SESSION_VERIFIED_USER) === (int) $user->id;
    }

    public function loggedInWithoutPrivilege(): bool
    {
        return session()->has(self::SESSION_PRIVILEGED_AT_LOGIN)
            && session(self::SESSION_PRIVILEGED_AT_LOGIN) === false;
    }

    public function requiresPrivilegedChallenge(User $user, bool $viaRemember): bool
    {
        if (! $user->canAccessAdmin() || ! $user->hasTwoFactorEnabled()) {
            return false;
        }

        return $viaRemember || $this->loggedInWithoutPrivilege();
    }
}
