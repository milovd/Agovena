<?php

declare(strict_types=1);

use App\Agovena\Abuse\SecurityAbuseService;
use Illuminate\Support\Facades\Artisan;

it('recovers a blocked IP through the operator command without echoing the IP', function (): void {
    $ip = '192.0.2.77';
    $abuse = app(SecurityAbuseService::class);
    $abuse->blockIp($ip, 'operator test');

    $exit = Artisan::call('agovena:abuse-recover', ['ip' => $ip]);

    expect($exit)->toBe(0)
        ->and($abuse->isIpBlocked($ip))->toBeFalse()
        ->and($abuse->isIpAllowed($ip))->toBeTrue();
    expect(Artisan::output())->not->toContain($ip);
});
