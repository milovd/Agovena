<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Api\ApiError;
use App\Agovena\Api\ApiTokenAbilities;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureApiTokenAbility
{
    public function handle(Request $request, Closure $next, string $ability = ''): Response
    {
        if (! filled($request->bearerToken())) {
            return $next($request);
        }

        $requiredAbility = $ability !== ''
            ? $ability
            : ApiTokenAbilities::forRoute((string) $request->route()?->getName());
        if ($requiredAbility === null) {
            return ApiError::json(
                'insufficient_scope',
                __('api.insufficient_scope'),
                403,
                ['required_ability' => ['unmapped_api_route']],
            );
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return ApiError::json('unauthenticated', __('api.unauthenticated'), 401);
        }

        if ($user->tokenCan($requiredAbility)) {
            return $next($request);
        }

        return ApiError::json(
            'insufficient_scope',
            __('api.insufficient_scope'),
            403,
            ['required_ability' => [$requiredAbility]],
        );
    }
}
