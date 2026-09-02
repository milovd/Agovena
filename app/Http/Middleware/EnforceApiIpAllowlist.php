<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Api\ApiError;
use App\Agovena\Api\ApiIpAllowlist;
use App\Models\PersonalAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceApiIpAllowlist
{
    public function __construct(
        private readonly ApiIpAllowlist $allowlist,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tokenAllowlist = $this->tokenAllowlist($request);
        if ($tokenAllowlist !== null && ! $this->allowlist->allows($request->ip(), $tokenAllowlist)) {
            return ApiError::json('ip_not_allowed', __('api.ip_not_allowed'), 403);
        }

        return $next($request);
    }

    private function tokenAllowlist(Request $request): mixed
    {
        $bearerToken = $request->bearerToken();
        if (! is_string($bearerToken) || $bearerToken === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($bearerToken);

        return $token instanceof PersonalAccessToken ? $token->ip_allowlist : null;
    }
}
