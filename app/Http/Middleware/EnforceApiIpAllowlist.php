<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Api\ApiError;
use App\Agovena\Api\ApiIpAllowlist;
use App\Agovena\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceApiIpAllowlist
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly ApiIpAllowlist $allowlist,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $configured = $this->settings->get(ApiIpAllowlist::GROUP, ApiIpAllowlist::KEY, []);
        if (! $this->allowlist->allows($request->ip(), $configured)) {
            return ApiError::json('ip_not_allowed', __('api.ip_not_allowed'), 403);
        }

        return $next($request);
    }
}
