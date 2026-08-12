<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Permissions\SyncRegisteredPermissions;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps Spatie permissions in sync with the Agovena registry so owners
 * receive newly registered abilities (e.g. users.view / roles.view) without
 * re-running create-owner.
 */
final class SyncStaffPermissions
{
    public function __construct(private readonly SyncRegisteredPermissions $sync) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            ($this->sync)();
        } catch (\Throwable) {
            // Permissions tables may be missing during early install.
        }

        return $next($request);
    }
}
