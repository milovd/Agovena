<?php

declare(strict_types=1);

namespace Agovena\Modules\Digital\Http\Livewire\Customer;

use Agovena\Modules\Digital\Models\DigitalEntitlement;
use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class DownloadsIndex extends Component
{
    public function render(ThemeManager $themes)
    {
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        $entitlements = DigitalEntitlement::query()
            ->with(['asset', 'order'])
            ->whereNull('revoked_at')
            ->where(function ($q) use ($customer): void {
                $q->where('customer_id', $customer->id)
                    ->orWhere('customer_email', $customer->email);
            })
            ->orderByDesc('id')
            ->get();

        $theme = $themes->active();

        return view($theme->view('account.downloads'), [
            'theme' => $theme,
            'entitlements' => $entitlements,
            'accountSection' => 'downloads',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('digital::customer.title'),
            'theme' => $theme,
        ]);
    }
}
