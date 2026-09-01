<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Api\ApiIpAllowlist;
use App\Agovena\Api\ApiTokenAbilities;
use App\Agovena\Audit\AuditLogger;
use App\Agovena\Settings\SettingsRepository;
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
    use RequiresRecentPassword {
        confirmRecentPassword as protected confirmRecentPasswordAfterAuthorization;
    }

    public string $apiIpAllowlist = '';

    public function mount(SettingsRepository $settings, ApiIpAllowlist $allowlist): void
    {
        $this->authorize('api.tokens');
        try {
            $this->apiIpAllowlist = implode(PHP_EOL, $allowlist->normalizeStored(
                $settings->get(ApiIpAllowlist::GROUP, ApiIpAllowlist::KEY, []),
            ));
        } catch (\InvalidArgumentException) {
            $this->apiIpAllowlist = '';
            $this->addError('apiIpAllowlist', __('admin.api_tokens.ip_allowlist_invalid'));
        }
    }

    public function create(): void
    {
        $this->authorize('api.tokens');
        $this->resetTokenEditor();
        $this->showTokenForm = true;
    }

    public function edit(int $tokenId, ApiIpAllowlist $allowlist): void
    {
        $this->authorize('api.tokens');
        $token = $this->tokenOwner()->tokens()->whereKey($tokenId)->firstOrFail();

        $this->editingTokenId = $token->id;
        $this->tokenName = $token->name;
        $this->token_name = $token->name;
        $this->selectedAbilities = ApiTokenAbilities::normalize(
            is_array($token->abilities) && $token->abilities !== []
                ? $token->abilities
                : [ApiTokenAbilities::ALL],
        );
        $this->tokenIpAllowlist = '';
        try {
            $this->tokenIpAllowlist = implode(PHP_EOL, $allowlist->normalizeStored($token->ip_allowlist ?? []));
        } catch (\InvalidArgumentException) {
            $this->addError('tokenIpAllowlist', __('admin.api_tokens.ip_allowlist_invalid'));
        }
        $this->showTokenForm = true;
        $this->resetValidation('tokenName');
        $this->resetValidation('selectedAbilities');
    }

    public function cancelTokenForm(): void
    {
        $this->authorize('api.tokens');
        $this->resetTokenEditor();
    }

    public function revokeToken(int $tokenId, AuditLogger $audit): void
    {
        $this->authorize('api.tokens');

        if (! $this->requireRecentPassword('completeTokenRevocation', ['tokenId' => $tokenId])) {
            return;
        }

        $this->revokePersonalAccessToken($tokenId, $audit);
    }

    public function completeTokenRevocation(int $tokenId, AuditLogger $audit): void
    {
        $this->authorize('api.tokens');
        if (! $this->requireRecentPassword('completeTokenRevocation', ['tokenId' => $tokenId])) {
            return;
        }

        $this->revokePersonalAccessToken($tokenId, $audit);
    }

    public function confirmRecentPassword(): void
    {
        $this->authorize('api.tokens');
        $this->confirmRecentPasswordAfterAuthorization();
    }

    public function render(AdminRegistrar $admin)
    {
        $this->authorize('api.tokens');

        return view('livewire.admin.system.api-tokens', [
            'tokens' => $this->tokens(),
            'abilityGroups' => ApiTokenAbilities::groupedDefinitions(),
        ])->layout('layouts.admin', [
            'title' => __('admin.api_tokens.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    public function saveIpAllowlist(SettingsRepository $settings, ApiIpAllowlist $allowlist, AuditLogger $audit): void
    {
        $this->authorize('api.tokens');

        if (! $this->requireRecentPassword('saveIpAllowlist')) {
            return;
        }

        $this->validate([
            'apiIpAllowlist' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $ips = $allowlist->parse($this->apiIpAllowlist);
        } catch (\InvalidArgumentException) {
            $this->addError('apiIpAllowlist', __('admin.api_tokens.ip_allowlist_invalid'));

            return;
        }

        try {
            $previous = $allowlist->normalizeStored(
                $settings->get(ApiIpAllowlist::GROUP, ApiIpAllowlist::KEY, []),
            );
        } catch (\InvalidArgumentException) {
            $previous = null;
        }
        $settings->set(ApiIpAllowlist::GROUP, ApiIpAllowlist::KEY, $ips);
        $audit->log('security.api_ip_allowlist_updated', $this->tokenOwner(), [
            'ip_count' => count($ips),
            'mode' => $ips === [] ? 'allow_all' : 'allow_list',
        ], [
            'ip_count' => is_array($previous) ? count($previous) : 0,
            'mode' => $previous === null ? 'invalid' : ($previous === [] ? 'allow_all' : 'allow_list'),
        ], [
            'ip_count' => count($ips),
            'mode' => $ips === [] ? 'allow_all' : 'allow_list',
        ]);
        session()->flash('status', __('admin.api_tokens.ip_allowlist_saved'));
    }

    protected function tokenOwner(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
