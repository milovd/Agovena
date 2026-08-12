<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Agovena\Installation\InstallationState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAgovenaInstalled
{
    public function __construct(private readonly InstallationState $state) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->state->notInstalled()) {
            return redirect()->route('install');
        }

        return $next($request);
    }
}
