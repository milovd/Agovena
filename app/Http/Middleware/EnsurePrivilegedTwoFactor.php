<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Auth\TotpTwoFactor;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePrivilegedTwoFactor
{
    public function __construct(private readonly TotpTwoFactor $totp) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('agovena.security.privileged_two_factor', true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('customer.security');
        }

        if ($this->totp->requiresPrivilegedChallenge($user, Auth::viaRemember())) {
            return $this->beginChallenge($request, $user);
        }

        return $next($request);
    }

    private function beginChallenge(Request $request, User $user): Response
    {
        $intended = $request->fullUrl();
        Auth::logout();
        $request->session()->put([
            TotpTwoFactor::SESSION_PENDING_ID => $user->id,
            TotpTwoFactor::SESSION_PENDING_REMEMBER => true,
            TotpTwoFactor::SESSION_PENDING_INTENDED => $intended,
        ]);

        return redirect()->route('two-factor.challenge');
    }
}
