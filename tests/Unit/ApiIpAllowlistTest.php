<?php

declare(strict_types=1);

use App\Agovena\Api\ApiIpAllowlist;

test('api ip allowlist normalizes unique ipv4 and ipv6 addresses', function (): void {
    expect(app(ApiIpAllowlist::class)->parse("203.0.113.10\n2001:0db8:0:0:0:0:0:1\n203.0.113.10"))
        ->toBe(['203.0.113.10', '2001:db8::1']);
});

test('empty api ip allowlist allows every valid request address', function (): void {
    $allowlist = app(ApiIpAllowlist::class);

    expect($allowlist->allows('203.0.113.10', []))->toBeTrue()
        ->and($allowlist->allows('2001:db8::1', ''))->toBeTrue()
        ->and($allowlist->allows('203.0.113.10', " \n,\t"))->toBeTrue()
        ->and($allowlist->allows('203.0.113.10', [' ', '']))->toBeTrue();
});

test('configured api ip allowlist denies every address that is not listed', function (): void {
    $allowlist = app(ApiIpAllowlist::class);

    expect($allowlist->allows('203.0.113.10', ['203.0.113.10']))->toBeTrue()
        ->and($allowlist->allows('203.0.113.11', ['203.0.113.10']))->toBeFalse()
        ->and($allowlist->allows('2001:db8::1', ['2001:0db8:0:0:0:0:0:1']))->toBeTrue();
});

test('api ip allowlist rejects invalid addresses', function (): void {
    expect(fn (): array => app(ApiIpAllowlist::class)->parse("203.0.113.10\nnot-an-ip"))
        ->toThrow(InvalidArgumentException::class);
});
