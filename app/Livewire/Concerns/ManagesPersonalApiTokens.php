<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Agovena\Api\ApiIpAllowlist;
use App\Agovena\Api\ApiTokenAbilities;
use App\Agovena\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

trait ManagesPersonalApiTokens
{
    public string $tokenName = '';

    /** @var list<string> */
    public array $selectedAbilities = [ApiTokenAbilities::ALL];

    public string $tokenIpAllowlist = '';

    public ?int $editingTokenId = null;

    public bool $showTokenForm = false;

    /** @deprecated Kept for backwards-compatible Livewire payloads. */
    public string $token_name = '';

    public ?string $plainTextToken = null;

    public function saveToken(AuditLogger $audit, ApiIpAllowlist $allowlist): void
    {
        $this->authorize('api.tokens');
        $this->syncLegacyTokenName();

        if (! $this->requireRecentPassword('completeTokenSave')) {
            return;
        }

        $this->completeTokenSave($audit, $allowlist);
    }

    public function completeTokenSave(AuditLogger $audit, ApiIpAllowlist $allowlist): void
    {
        $this->authorize('api.tokens');
        $this->syncLegacyTokenName();

        if (! $this->requireRecentPassword('completeTokenSave')) {
            return;
        }

        $data = $this->validate([
            'tokenName' => ['required', 'string', 'max:80'],
            'selectedAbilities' => ['required', 'array', 'min:1'],
            'selectedAbilities.*' => ['string', Rule::in(ApiTokenAbilities::keys())],
            'tokenIpAllowlist' => ['nullable', 'string', 'max:5000'],
        ]);

        try {
            $ipAllowlist = $allowlist->parse($data['tokenIpAllowlist']);
        } catch (\InvalidArgumentException) {
            $this->addError('tokenIpAllowlist', __('admin.api_tokens.ip_allowlist_invalid'));

            return;
        }

        $abilities = ApiTokenAbilities::normalize($data['selectedAbilities']);
        if ($abilities === []) {
            $this->addError('selectedAbilities', __('admin.api_tokens.ability_required'));

            return;
        }

        if ($this->editingTokenId === null) {
            $user = $this->tokenOwner();
            $new = $user->createToken($data['tokenName'], $abilities);
            $new->accessToken->forceFill(['ip_allowlist' => $ipAllowlist])->save();
            $this->plainTextToken = $new->plainTextToken;
            $audit->log('api_token.created', $user, [
                'token_name' => $data['tokenName'],
                'token_id' => $new->accessToken->id,
                'abilities' => $abilities,
                'ip_count' => count($ipAllowlist),
                'ip_mode' => $ipAllowlist === [] ? 'allow_all' : 'allow_list',
            ]);
            session()->flash('status', __('admin.api_tokens.created'));
        } else {
            $token = $this->tokenOwner()->tokens()->whereKey($this->editingTokenId)->first();
            if (! $token instanceof PersonalAccessToken) {
                return;
            }

            $before = [
                'token_name' => $token->name,
                'abilities' => $token->abilities,
                'ip_allowlist' => $token->ip_allowlist,
            ];
            $token->forceFill([
                'name' => $data['tokenName'],
                'abilities' => $abilities,
                'ip_allowlist' => $ipAllowlist,
            ])->save();
            $audit->log('api_token.updated', $token, [
                'token_id' => $token->id,
                'abilities' => $abilities,
                'ip_count' => count($ipAllowlist),
                'ip_mode' => $ipAllowlist === [] ? 'allow_all' : 'allow_list',
            ], $before, [
                'token_name' => $token->name,
                'abilities' => $token->abilities,
                'ip_allowlist' => $token->ip_allowlist,
            ]);
            session()->flash('status', __('admin.api_tokens.updated'));
        }

        $this->resetTokenEditor();
    }

    public function createToken(AuditLogger $audit): void
    {
        $this->syncLegacyTokenName();
        $this->authorize('api.tokens');

        if (! $this->requireRecentPassword('completeTokenCreation')) {
            return;
        }

        $this->completeTokenCreation($audit, app(ApiIpAllowlist::class));
    }

    public function completeTokenCreation(AuditLogger $audit, ApiIpAllowlist $allowlist): void
    {
        $this->authorize('api.tokens');
        $this->syncLegacyTokenName();

        if (! $this->requireRecentPassword('completeTokenCreation')) {
            return;
        }

        $this->editingTokenId = null;
        $this->completeTokenSave($audit, $allowlist);
    }

    public function revokeToken(int $tokenId, AuditLogger $audit): void
    {
        $this->revokePersonalAccessToken($tokenId, $audit);
    }

    protected function revokePersonalAccessToken(int $tokenId, AuditLogger $audit): void
    {
        $token = $this->tokenOwner()->tokens()->whereKey($tokenId)->first();
        if (! $token instanceof PersonalAccessToken) {
            return;
        }

        $audit->log('api_token.revoked', $this->tokenOwner(), [
            'token_name' => $token->name,
            'token_id' => $token->id,
        ]);
        $token->delete();
        if ($this->plainTextToken !== null) {
            $this->plainTextToken = null;
        }
        session()->flash('status', __('admin.api_tokens.revoked'));
    }

    /** @return Collection<int, PersonalAccessToken> */
    protected function tokens(): Collection
    {
        return $this->tokenOwner()->tokens()->latest('id')->get();
    }

    protected function resetTokenEditor(): void
    {
        $this->editingTokenId = null;
        $this->tokenName = '';
        $this->token_name = '';
        $this->selectedAbilities = [ApiTokenAbilities::ALL];
        $this->tokenIpAllowlist = '';
        $this->showTokenForm = false;
        $this->resetValidation();
    }

    private function syncLegacyTokenName(): void
    {
        if ($this->tokenName === '' && $this->token_name !== '') {
            $this->tokenName = $this->token_name;
        }
    }

    abstract protected function tokenOwner(): User;
}
