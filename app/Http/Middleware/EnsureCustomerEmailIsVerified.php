<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCustomerEmailIsVerified
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customer = Auth::guard('customer')->user();

        if ($customer === null || ! $customer->hasVerifiedEmail()) {
            return redirect()->route('customer.verification.notice');
        }

        return $next($request);
    }
}
