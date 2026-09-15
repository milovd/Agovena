<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

it('uses Livewire’s CSP-safe Alpine bundle', function (): void {
    expect(Config::get('livewire.csp_safe'))->toBeTrue();

    $page = $this->get('/');
    preg_match('/<script src="([^"]+\/livewire\.js[^"]*)"/', $page->getContent(), $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    $response = $this->get($matches[1]);

    expect(app('livewire')->isCspSafe())->toBeTrue();
    $response->assertOk();
});
