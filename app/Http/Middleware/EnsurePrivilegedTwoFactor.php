<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePrivilegedTwoFactor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('agovena.security.privileged_two_factor', true)) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->routeIs('admin.security.two-factor')) {
            return $next($request);
        }

        return redirect()->route('admin.security.two-factor');
    }
}
