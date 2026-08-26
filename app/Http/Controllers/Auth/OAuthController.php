<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Agovena\Auth\OAuth\OAuthIdentityService;
use App\Agovena\Auth\OAuth\OAuthProviderAvailability;
use App\Agovena\Auth\OAuth\OAuthProviderRegistry;
use App\Agovena\Auth\OAuth\OAuthStateStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class OAuthController
{
    public function redirect(
        string $provider,
        Request $request,
        OAuthProviderRegistry $providers,
        OAuthStateStore $states,
        OAuthProviderAvailability $availability,
    ): RedirectResponse {
        $definition = $providers->get(strtolower($provider));
        $config = config('services.oauth.'.$definition->id, []);
        if (! is_array($config) || ! $availability->enabled($definition) || (string) ($config['client_id'] ?? '') === '') {
            throw ValidationException::withMessages(['provider' => 'The OAuth provider is not enabled.']);
        }

        $redirect = $request->query('redirect', route('login'));
        if (! is_string($redirect)) {
            throw ValidationException::withMessages(['redirect' => 'The OAuth redirect is not allowed.']);
        }
        $state = $states->issueWithNonce($definition->id, $redirect);
        $parameters = [
            'client_id' => (string) $config['client_id'],
            'redirect_uri' => route('oauth.callback', ['provider' => $definition->id]),
            'response_type' => 'code',
            'scope' => implode(' ', $definition->scopes),
            'state' => $state['state'],
        ];
        if ($definition->oidc) {
            $parameters['nonce'] = $state['nonce'];
        }

        return redirect()->away($definition->authorizationEndpoint.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986));
    }

    public function callback(
        string $provider,
        Request $request,
        OAuthIdentityService $identities,
    ): RedirectResponse {
        $code = $request->query('code');
        $state = $request->query('state');
        if (! is_string($code) || ! is_string($state)) {
            throw ValidationException::withMessages(['provider' => 'The OAuth callback is invalid.']);
        }

        $result = $identities->handleCallback($provider, $state, $code);

        return redirect()->to($result->redirect);
    }
}
