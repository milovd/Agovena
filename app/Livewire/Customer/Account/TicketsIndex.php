<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

final class TicketsIndex extends Component
{
    use WithPagination;

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();
        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return view($theme->view('account.tickets.index'), [
            'tickets' => $customer->tickets()->latest('last_reply_at')->paginate(20),
            'accountSection' => 'tickets',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('customer.tickets.title'),
            'theme' => $theme,
        ]);
    }
}
