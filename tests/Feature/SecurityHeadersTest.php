<?php

test('web responses include production security headers', function () {
    $response = $this->get('/');

    $response->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toBeString()
        ->and($csp)->toContain("default-src 'self'")
        ->and($csp)->toContain("form-action 'self'")
        ->and($csp)->toContain("frame-ancestors 'none'")
        ->and($csp)->not->toContain('upgrade-insecure-requests');
});

test('hsts is not set on insecure requests', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
});
