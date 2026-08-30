<?php

declare(strict_types=1);

use Agovena\Extensions\NamecheapDomain\HttpNamecheapApi;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('parses the Namecheap XML availability response without exposing credentials', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-domain');

    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('namecheap-domain', 'api_user', 'fixture-user');
    $settings->set('namecheap-domain', 'api_key', '[REDACTED]', secret: true);
    $settings->set('namecheap-domain', 'username', 'fixture-user');
    $settings->set('namecheap-domain', 'client_ip', '198.51.100.10');
    $settings->set('namecheap-domain', 'sandbox', true);
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
