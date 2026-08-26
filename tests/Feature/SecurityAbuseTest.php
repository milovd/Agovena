<?php

declare(strict_types=1);

use App\Agovena\Abuse\SecurityAbuseService;
use App\Http\Middleware\EnforceAbusePolicy;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

it('stores hashed IP rules and lets an explicit allowlist override a block', function (): void {
    $abuse = app(SecurityAbuseService::class);
    $ip = '203.0.113.10';

    $abuse->blockIp($ip, 'automated abuse');
    expect($abuse->isIpBlocked($ip))->toBeTrue();
    $this->assertDatabaseMissing('security_ip_rules', ['ip_hash' => $ip]);

    $abuse->allowIp($ip, 'operator recovery');
    expect($abuse->isIpAllowed($ip))->toBeTrue()->and($abuse->isIpBlocked($ip))->toBeFalse();
});

it('protects checkout and OAuth routes with abuse and rate policies', function (): void {
    $checkoutMiddleware = app('router')->getRoutes()->getByName('storefront.checkout')->gatherMiddleware();
    $oauthMiddleware = app('router')->getRoutes()->getByName('oauth.redirect')->gatherMiddleware();

    expect($checkoutMiddleware)->toContain('abuse')->toContain('throttle:checkout')
        ->and($oauthMiddleware)->toContain('abuse')->toContain('throttle:oauth');
});

it('applies the abuse policy to the web middleware stack', function (): void {
    app(SecurityAbuseService::class)->blockIp('192.0.2.44', 'rate abuse');

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.44'])
        ->get('/login')
        ->assertTooManyRequests();
});

it('blocks an abusive request and suspended user while allowing normal traffic', function (): void {
    $abuse = app(SecurityAbuseService::class);
    $middleware = app(EnforceAbusePolicy::class);
    $next = static fn (Request $request) => response('ok');
    $request = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.7']);

    expect($middleware->handle($request, $next)->getStatusCode())->toBe(200);

    $abuse->blockIp('198.51.100.7', 'rate abuse');
    expect(fn () => $middleware->handle($request, $next))
        ->toThrow(TooManyRequestsHttpException::class);

    $user = User::factory()->create();
    $abuse->suspendUser($user, 'manual review');
    $authenticated = Request::create('/', 'GET', server: ['REMOTE_ADDR' => '198.51.100.8']);
    $authenticated->setUserResolver(static fn (): User => $user);

    expect(fn () => $middleware->handle($authenticated, $next))
        ->toThrow(HttpException::class);
});
