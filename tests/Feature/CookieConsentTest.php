<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

it('records a consent decision with hashed request metadata and a preference cookie', function (): void {
    $response = $this->post(route('privacy.consent'), [
        'choice' => 'all',
    ]);

    $response->assertRedirect(route('storefront.home'))
        ->assertCookie('agovena_consent');

    $event = DB::table('consent_events')->first();

    expect($event)->not->toBeNull()
        ->and($event->choice)->toBe('all')
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
            'functional' => true,
            'marketing' => true,
            'necessary' => true,
        ]);
});

it('rejects unsupported consent choices', function (): void {
    $this->from(route('storefront.home'))
        ->post(route('privacy.consent'), ['choice' => 'marketing_only'])
        ->assertSessionHasErrors('choice');

    expect(DB::table('consent_events')->count())->toBe(0);
});

it('shows the consent banner until the visitor makes a choice', function (): void {
    $this->get(route('storefront.home'))
        ->assertOk()
        ->assertSee('cookie-consent', false)
        ->assertSee(__('storefront.cookie_consent.accept_all'));
});
