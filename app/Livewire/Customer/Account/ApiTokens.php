<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Account;

use App\Agovena\Theme\ThemeManager;
use App\Livewire\Concerns\ManagesPersonalApiTokens;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class ApiTokens extends Component
{
    use ManagesPersonalApiTokens;

    public function render(ThemeManager $themes)
    {
        $theme = $themes->active();

        return view($theme->view('account.api-tokens'), [
            'theme' => $theme,
            'tokens' => $this->tokens(),
            'accountSection' => 'api-tokens',
        ])->layout($theme->view('layouts.storefront'), [
            'title' => __('admin.api_tokens.title'),
            'theme' => $theme,
        ]);
    }

    protected function tokenOwner(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
