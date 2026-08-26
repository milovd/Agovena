<?php

declare(strict_types=1);

namespace App\Agovena\Auth\OAuth;

use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OAuthIdentityService
{
    public function __construct(
        private readonly OAuthStateStore $states,
        private readonly OAuthProviderRegistry $providers,
        private readonly OAuthClient $client,
        private readonly OAuthOidcTokenVerifier $oidcVerifier,
        private readonly OAuthProviderAvailability $availability,
    ) {}

    public function handleCallback(string $providerId, string $state, string $code): OAuthCallbackResult
    {
        $statePayload = $this->states->consumePayload($providerId, $state);
        if ($statePayload === null || trim($code) === '') {
            throw ValidationException::withMessages(['provider' => 'The OAuth callback is invalid or expired.']);
        }

        $provider = $this->providers->get(strtolower(trim($providerId)));
        if (! $this->availability->enabled($provider)) {
            throw ValidationException::withMessages(['provider' => 'The OAuth provider is not enabled.']);
        }
        $redirectUri = route('oauth.callback', ['provider' => $provider->id]);
        $tokens = $this->client->exchangeCode($provider, $code, $redirectUri);
        $profile = $this->client->userInfo($provider, (string) $tokens['access_token']);
        if ($provider->oidc) {
            $idToken = $tokens['id_token'] ?? null;
            if (! is_string($idToken)) {
                throw ValidationException::withMessages(['provider' => 'The OIDC token response is incomplete.']);
            }
            $profile = array_merge($profile, $this->oidcVerifier->verify($provider, $idToken, $statePayload['nonce']));
        }

        $subject = trim((string) ($profile['sub'] ?? $profile['id'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $verified = filter_var($profile['email_verified'] ?? $profile['verified'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($subject === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $verified) {
            throw ValidationException::withMessages(['provider' => 'The OAuth profile is missing a verified email identity.']);
        }

        $identity = OAuthIdentity::query()->where('provider', $provider->id)->where('subject', $subject)->first();
        $linked = false;
        if ($identity instanceof OAuthIdentity) {
            $user = User::query()->findOrFail($identity->user_id);
        } elseif (Auth::check()) {
            $user = Auth::user();
            if (! $user instanceof User) {
                throw ValidationException::withMessages(['provider' => 'The authenticated account is invalid.']);
            }
            if (OAuthIdentity::query()->where('user_id', $user->id)->where('provider', $provider->id)->exists()) {
                throw ValidationException::withMessages(['provider' => 'This account already has an identity for this provider.']);
            }
            $linked = true;
        } else {
            $user = User::query()->where('email', $email)->first();
            if (! $user instanceof User) {
                $user = User::query()->create([
                    'name' => trim((string) ($profile['global_name'] ?? $profile['username'] ?? $email)),
                    'email' => $email,
                    'password' => Str::random(64),
                    'email_verified_at' => now(),
                ]);
            }
        }

        if (! $identity instanceof OAuthIdentity) {
            OAuthIdentity::query()->create([
                'user_id' => $user->id,
                'provider' => $provider->id,
                'subject' => $subject,
                'email' => $email,
                'name' => trim((string) ($profile['global_name'] ?? $profile['username'] ?? '')) ?: null,
                'avatar_url' => is_string($profile['avatar_url'] ?? null) ? $profile['avatar_url'] : null,
                'last_login_at' => now(),
            ]);
        } else {
            $identity->update(['last_login_at' => now(), 'email' => $email]);
        }

        Auth::login($user, false);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        return new OAuthCallbackResult($statePayload['redirect'], (int) $user->id, $linked);
    }
}
