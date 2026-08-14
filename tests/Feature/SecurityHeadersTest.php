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

test('local debug csp allows vite including the hot-file origin', function () {
    $this->app['env'] = 'local';
    config(['app.debug' => true]);

    $hot = public_path('hot');
    $previous = is_file($hot) ? file_get_contents($hot) : false;
    file_put_contents($hot, "http://127.0.0.1:5174\n");

    try {
        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        expect($csp)->toBeString()
            ->and($csp)->toContain('http://localhost:5173')
            ->and($csp)->toContain('http://127.0.0.1:5173')
            ->and($csp)->toContain('http://[::1]:5173')
            ->and($csp)->toContain('ws://[::1]:5173')
            ->and($csp)->toContain('http://127.0.0.1:5174')
            ->and($csp)->toContain('ws://127.0.0.1:5174');
    } finally {
        if ($previous === false) {
            @unlink($hot);
        } else {
            file_put_contents($hot, $previous);
        }
    }
});
