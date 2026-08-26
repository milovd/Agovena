<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Abuse\SecurityAbuseService;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class EnforceAbusePolicy
{
    public function __construct(private readonly SecurityAbuseService $abuse) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        if (is_string($ip) && $this->abuse->isIpBlocked($ip)) {
            throw new TooManyRequestsHttpException(60, 'Request rate limited.');
        }

        $user = $request->user();
        if ($user instanceof User && $this->abuse->isUserSuspended($user)) {
            throw new HttpException(403, 'Account access is suspended.');
        }

        return $next($request);
    }
}
