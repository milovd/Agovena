<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Agovena\Customer\AttachGuestOrdersToCustomer;
use App\Models\Customer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class EmailVerificationController
{
    public function __invoke(Request $request, AttachGuestOrdersToCustomer $attachGuestOrders): RedirectResponse
    {
        /** @var Customer|null $customer */
        $customer = Auth::guard('customer')->user();

        if ($customer === null) {
            return redirect()->route('customer.login');
        }

        if (! hash_equals((string) $request->route('id'), (string) $customer->getKey())) {
            abort(403);
        }

        if (! hash_equals((string) $request->route('hash'), sha1($customer->getEmailForVerification()))) {
            abort(403);
        }

        if ($customer->hasVerifiedEmail()) {
            return redirect()->route('customer.account');
        }

        if ($customer->markEmailAsVerified()) {
            event(new Verified($customer));
        }

        $attachGuestOrders->handle($customer->fresh() ?? $customer);

        return redirect()
            ->route('customer.account')
            ->with('status', __('customer.auth.verified'));
    }
}
