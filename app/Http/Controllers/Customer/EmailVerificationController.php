<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Agovena\Customer\AttachGuestOrdersToCustomer;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class EmailVerificationController
{
    public function __invoke(Request $request, AttachGuestOrdersToCustomer $attachGuestOrders): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        if (! hash_equals((string) $request->route('id'), (string) $user->getKey())) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('customer.account');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $customer = $user->ensureCustomer();
        $attachGuestOrders->handle($customer);

        return redirect()
            ->route('customer.account')
            ->with('status', __('customer.auth.verified'));
    }
}
