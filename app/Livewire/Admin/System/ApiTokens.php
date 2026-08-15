<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Audit\AuditLogger;
use App\Livewire\Concerns\ManagesPersonalApiTokens;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

final class ApiTokens extends Component
{
    use AuthorizesRequests;
    use ManagesPersonalApiTokens;
    use RequiresRecentPassword;

    public function mount(): void
    {
        $this->authorize('api.tokens');
    }

    public function createToken(AuditLogger $audit): void
    {
        if (! $this->requireRecentPassword('createPersonalAccessToken')) {
            return;
        }

        $this->createPersonalAccessToken($audit);
    }

    public function revokeToken(int $tokenId, AuditLogger $audit): void
    {
        if (! $this->requireRecentPassword('revokePersonalAccessToken', ['tokenId' => $tokenId])) {
            return;
        }

        $this->revokePersonalAccessToken($tokenId, $audit);
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
