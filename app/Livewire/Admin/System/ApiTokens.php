<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Livewire\Concerns\ManagesPersonalApiTokens;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class ApiTokens extends Component
{
    use AuthorizesRequests;
    use ManagesPersonalApiTokens;

    public function mount(): void
    {
        $this->authorize('api.tokens');
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.system.api-tokens', [
            'tokens' => $this->tokens(),
        ])->layout('layouts.admin', [
            'title' => __('admin.api_tokens.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    protected function tokenOwner(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
