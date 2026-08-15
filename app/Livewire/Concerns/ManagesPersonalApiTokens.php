<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Agovena\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

trait ManagesPersonalApiTokens
{
    public string $token_name = '';

    public ?string $plainTextToken = null;

    public function createToken(AuditLogger $audit): void
    {
        $this->createPersonalAccessToken($audit);
    }

    public function revokeToken(int $tokenId, AuditLogger $audit): void
    {
        $this->revokePersonalAccessToken($tokenId, $audit);
    }

    protected function createPersonalAccessToken(AuditLogger $audit): void
    {
        $data = $this->validate([
            'token_name' => ['required', 'string', 'max:80'],
        ]);

        $user = $this->tokenOwner();
        $new = $user->createToken($data['token_name'], ['*']);
        $this->plainTextToken = $new->plainTextToken;
        $this->reset('token_name');
        $audit->log('api_token.created', $user, [
            'token_name' => $data['token_name'],
            'token_id' => $new->accessToken->id,
        ]);
        session()->flash('status', __('admin.api_tokens.created'));
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

    abstract protected function tokenOwner(): User;
}
