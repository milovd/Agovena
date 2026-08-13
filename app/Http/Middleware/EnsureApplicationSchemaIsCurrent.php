<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Installed stores whose application code is newer than the database schema
 * must not expose random SQL errors. Migrations are never run from HTTP.
 */
final class EnsureApplicationSchemaIsCurrent
{
    public function __construct(
        private readonly InstallationState $state,
        private readonly ApplicationSchemaStatus $schema,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->notInstalled() || $this->isExempt($request)) {
            return $next($request);
        }

        try {
            if ($this->schema->isCurrent()) {
                return $next($request);
            }
        } catch (Throwable) {
            return $next($request);
        }

        return $this->upgradeRequired($request);
    }

    private function isExempt(Request $request): bool
    {
        if ($request->routeIs('schema.update-required', 'install')) {
            return true;
        }

        return $request->is('install')
            || $request->is('install/*')
            || $request->is('up');
    }

    private function upgradeRequired(Request $request): Response
    {
        if ($request->expectsJson() || $request->is('livewire/*')) {
            return response()->json([
                'message' => __('schema.update_required.title'),
            ], 503);
        }

        return response()->view('schema.update-required', $this->schema->viewData(), 503);
    }
}
