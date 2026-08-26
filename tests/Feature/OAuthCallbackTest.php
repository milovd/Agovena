<?php

declare(strict_types=1);

use App\Agovena\Auth\OAuth\OAuthIdentityService;
use App\Agovena\Auth\OAuth\OAuthStateStore;
use App\Models\OAuthIdentity;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

function configureDiscordOAuth(): void
{
    config()->set('services.oauth.discord', [
        'enabled' => true,
        'client_id' => 'discord-client-id',
        'client_secret' => 'discord-client-secret',
    ]);
}

it('shows only explicitly enabled OAuth providers on the login page', function (): void {
    configureDiscordOAuth();
    config()->set('services.oauth.google.enabled', false);

    $response = $this->get(route('login'));

    $response->assertOk()
        ->assertSee(route('oauth.redirect', ['provider' => 'discord']), false)
        ->assertDontSee(route('oauth.redirect', ['provider' => 'google']), false);
});

it('exchanges a Discord code and creates a verified OAuth user', function (): void {
    configureDiscordOAuth();
    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-access-token'], 200),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-user-1',
            'email' => 'discord@example.test',
            'username' => 'Discord User',
            'verified' => true,
        ], 200),
    ]);

    $state = app(OAuthStateStore::class)->issue('discord', '/account/security');
    $result = app(OAuthIdentityService::class)->handleCallback('discord', $state, 'authorization-code');

    expect($result->redirect)->toBe('/account/security')
        ->and(Auth::user())->toBeInstanceOf(User::class)
        ->and(OAuthIdentity::query()->where('provider', 'discord')->where('subject', 'discord-user-1')->exists())->toBeTrue();
});

it('links a Discord identity to the authenticated user and rejects state replay', function (): void {
    configureDiscordOAuth();
    $user = User::factory()->create(['email' => 'owner@example.test']);
    Auth::login($user);
    Http::fake([
        'https://discord.com/api/oauth2/token' => Http::response(['access_token' => 'discord-access-token'], 200),
        'https://discord.com/api/users/@me' => Http::response([
            'id' => 'discord-user-2',
            'email' => 'owner@example.test',
            'username' => 'Owner',
            'verified' => true,
        ], 200),
    ]);

    $state = app(OAuthStateStore::class)->issue('discord', '/account/security');
    app(OAuthIdentityService::class)->handleCallback('discord', $state, 'authorization-code');

    expect(OAuthIdentity::query()->where('user_id', $user->id)->where('subject', 'discord-user-2')->exists())->toBeTrue()
        ->and(fn () => app(OAuthIdentityService::class)->handleCallback('discord', $state, 'authorization-code'))
        ->toThrow(ValidationException::class);
});
