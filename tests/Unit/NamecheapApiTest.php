<?php

declare(strict_types=1);

use Agovena\Extensions\NamecheapRegistrar\HttpNamecheapApi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('parses the Namecheap XML availability response without exposing credentials', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-registrar');

    putenv('AGOVENA_EXT_NAMECHEAP_REGISTRAR_API_USER=fixture-user');
    putenv('AGOVENA_EXT_NAMECHEAP_REGISTRAR_API_KEY=[REDACTED]');
    putenv('AGOVENA_EXT_NAMECHEAP_REGISTRAR_USERNAME=fixture-user');
    putenv('AGOVENA_EXT_NAMECHEAP_REGISTRAR_CLIENT_IP=198.51.100.10');
    putenv('AGOVENA_EXT_NAMECHEAP_REGISTRAR_SANDBOX=true');
    Http::fake([
        'https://api.sandbox.namecheap.com/xml.response' => Http::response(
            '<?xml version="1.0" encoding="UTF-8"?><ApiResponse Status="OK"><Errors/><CommandResponse><DomainCheckResult Domain="example.test" Available="true" RegistrationPrice="12.50" Currency="USD"/></CommandResponse></ApiResponse>',
            200,
            ['Content-Type' => 'application/xml'],
        ),
    ]);

    $result = app(HttpNamecheapApi::class)->check(['example.test']);

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://api.sandbox.namecheap.com/xml.response'
        && $request->method() === 'POST'
        && $request['Command'] === 'namecheap.domains.check'
        && $request['DomainList'] === 'example.test');
    expect($result)->toBe([
        'domains' => [[
            'domain' => 'example.test',
            'available' => true,
            'registration_price' => '12.50',
            'currency' => 'USD',
        ]],
    ]);
});
