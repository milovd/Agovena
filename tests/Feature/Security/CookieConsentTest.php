<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('records a consent decision with hashed request metadata and a preference cookie', function (): void {
    $response = $this->post(route('privacy.consent'), [
        'choice' => 'analytics',
    ]);

    $response->assertRedirect(route('storefront.home'))
        ->assertCookie('agovena_consent');

    $event = DB::table('consent_events')->first();

    expect($event)->not->toBeNull()
        ->and($event->choice)->toBe('analytics')
        ->and($event->source)->toBe('banner')
        ->and($event->ip_hash)->not->toBe('127.0.0.1')
        ->and($event->user_agent_hash)->not->toBeNull();

    $decisions = DB::table('consent_event_categories')
        ->where('consent_event_id', $event->id)
        ->pluck('decision', 'category')
        ->map(static fn ($decision): bool => (bool) $decision)
        ->all();

    expect($decisions)
        ->toBe([
            'analytics' => true,
            'functional' => false,
            'marketing' => false,
            'necessary' => true,
        ]);
});

it('records consent through the JSON contract used by the settings panel', function (): void {
    $response = $this->postJson(route('privacy.consent'), [
        'choice' => 'necessary',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('consent.choice', 'necessary')
        ->assertJsonStructure([
            'consent' => ['version', 'choice', 'categories', 'id', 'date'],
        ])
        ->assertCookie('agovena_consent');
});

it('records an analytics-only preference without enabling other optional categories', function (): void {
    $response = $this->postJson(route('privacy.consent'), [
        'choice' => 'analytics',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('consent.choice', 'analytics')
        ->assertJsonPath('consent.categories.necessary', true)
        ->assertJsonPath('consent.categories.functional', false)
        ->assertJsonPath('consent.categories.analytics', true)
        ->assertJsonPath('consent.categories.marketing', false);
});

it('rejects unsupported consent choices', function (): void {
    $this->from(route('storefront.home'))
        ->post(route('privacy.consent'), ['choice' => 'marketing_only'])
        ->assertSessionHasErrors('choice');

    $this->from(route('storefront.home'))
        ->post(route('privacy.consent'), ['choice' => 'all'])
        ->assertSessionHasErrors('choice');

    expect(DB::table('consent_events')->count())->toBe(0);
});

it('shows the consent banner until the visitor makes a choice', function (): void {
    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('store-cookie-consent', false)
        ->assertSee('data-cookie-settings', false)
        ->assertSee('data-cookie-panel', false)
        ->assertSee('data-cookie-tab="consent"', false)
        ->assertSee('data-cookie-tab="about"', false)
        ->assertSee(__('storefront.cookie_consent.accept'))
        ->assertSee(__('storefront.cookie_consent.customize'))
        ->assertDontSee('Google reCAPTCHA')
        ->assertDontSee('data-cookie-choice="all"', false);
});

it('serves the cookie policy link used by the consent experience', function (): void {
    $this->get('/cookies')
        ->assertOk()
        ->assertSee(__('storefront.cookie_consent.policy'), false)
        ->assertSee('data-cookie-open', false);
});

it('keeps cookie settings available after consent is saved', function (): void {
    $response = $this->withCookie('agovena_consent', json_encode([
        'version' => '1',
        'choice' => 'necessary',
        'categories' => [
            'necessary' => true,
            'functional' => false,
            'analytics' => false,
            'marketing' => false,
        ],
    ], JSON_THROW_ON_ERROR))->get(route('storefront.home'));

    $response
        ->assertOk()
        ->assertSee('data-cookie-open', false)
        ->assertSee('data-cookie-panel', false)
        ->assertSee('hidden', false);
});
