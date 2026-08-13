<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Global application gate: until Agovena is installed, only the installer
 * (and technically required Livewire/update endpoints) may run.
 */
final class EnsureAgovenaInstalled
{
    public function __construct(private readonly InstallationState $state) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->installed() || $this->isExempt($request)) {
            return $next($request);
        }

        if ($request->is('api') || $request->is('api/*')) {
            return response()->json([
                'message' => __('api.not_installed'),
                'code' => 'not_installed',
            ], 503);
        }

        return redirect()->route('install');
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs('install')) {
            return true;
        }

        return $request->is('install')
            || $request->is('install/*')
            || $request->is('livewire/*')
            || $request->is('livewire-*/**')
            || $request->is('up');
    }
}
