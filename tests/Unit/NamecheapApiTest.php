<?php

declare(strict_types=1);

use Agovena\Extensions\NamecheapDomain\HttpNamecheapApi;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const NAMECHEAP_FIXTURE_KEY = 'fixture-secret-key-0001';
const NAMECHEAP_SANDBOX_URL = 'https://api.sandbox.namecheap.com/xml.response';

function namecheapApiFixture(array $overrides = []): HttpNamecheapApi
{
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-domain');

    $settings = app(ExtensionSettingsRepository::class);
    $values = array_merge([
        'api_user' => 'fixture-user',
        'api_key' => NAMECHEAP_FIXTURE_KEY,
        'username' => 'fixture-user',
        'client_ip' => '198.51.100.10',
        'sandbox' => true,
    ], $overrides);
    foreach ($values as $key => $value) {
        $settings->set('namecheap-domain', $key, $value, secret: $key === 'api_key');
    }

    return app(HttpNamecheapApi::class);
}

function namecheapXml(string $commandResponse, string $status = 'OK', string $errors = '<Errors/>'): string
{
    return '<?xml version="1.0" encoding="utf-8"?>'
        .'<ApiResponse xmlns="http://api.namecheap.com/xml.response" Status="'.$status.'">'
        .$errors.'<Warnings/>'
        .$commandResponse
        .'<Server>FIXTURE</Server><GMTTimeDifference>--4:00</GMTTimeDifference><ExecutionTime>0.1</ExecutionTime>'
        .'</ApiResponse>';
}

/** @return array<string, string> */
function namecheapContactFixture(): array
{
    return [
        'FirstName' => 'Jane',
        'LastName' => 'Doe',
        'OrganizationName' => 'Fixture BV',
        'Address1' => 'Fixture Street 1',
        'Address2' => '',
        'City' => 'Ghent',
        'StateProvince' => 'Oost-Vlaanderen',
        'PostalCode' => '9000',
        'Country' => 'BE',
        'Phone' => '+32.470123456',
        'EmailAddress' => 'jane@example.test',
    ];
}

function namecheapExceptionChainText(Throwable $exception): string
{
    $text = '';
    for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
        $text .= get_class($current).': '.$current->getMessage()."\n";
    }

    return $text;
}

it('parses the documented domains.check response including premium attributes', function (): void {
    $api = namecheapApiFixture();
    Http::fake([
        NAMECHEAP_SANDBOX_URL => Http::response(namecheapXml(
            '<RequestedCommand>namecheap.domains.check</RequestedCommand><CommandResponse Type="namecheap.domains.check">'
            .'<DomainCheckResult Domain="example.com" Available="true" ErrorNo="0" Description="" IsPremiumName="false" PremiumRegistrationPrice="0" PremiumRenewalPrice="0" PremiumRestorePrice="0" PremiumTransferPrice="0" IcannFee="0" EapFee="0"/>'
            .'<DomainCheckResult Domain="us.xyz" Available="true" ErrorNo="0" Description="" IsPremiumName="true" PremiumRegistrationPrice="13000.0000" PremiumRenewalPrice="13000.0000" PremiumRestorePrice="65.0000" PremiumTransferPrice="13000.0000" IcannFee="0.0000" EapFee="0.0000"/>'
            .'</CommandResponse>'
        ), 200, ['Content-Type' => 'application/xml']),
    ]);

    $result = $api->check(['example.com', 'us.xyz']);

    Http::assertSent(static fn (Request $request): bool => $request->url() === NAMECHEAP_SANDBOX_URL
        && $request->method() === 'POST'
        && $request['Command'] === 'namecheap.domains.check'
        && $request['DomainList'] === 'example.com,us.xyz'
        && $request['ApiUser'] === 'fixture-user'
        && $request['UserName'] === 'fixture-user'
        && $request['ClientIp'] === '198.51.100.10'
        && ! str_contains($request->url(), NAMECHEAP_FIXTURE_KEY));
    expect($result['domains'])->toBe([
        ['domain' => 'example.com', 'available' => true, 'premium' => false, 'error_no' => '0', 'premium_registration_price' => null, 'eap_fee' => null, 'icann_fee' => null],
        ['domain' => 'us.xyz', 'available' => true, 'premium' => true, 'error_no' => '0', 'premium_registration_price' => '13000.0000', 'eap_fee' => null, 'icann_fee' => null],
    ]);
});

it('reads the account registration price from users.getPricing and caches it', function (): void {
    $api = namecheapApiFixture();
    Http::fake([
        NAMECHEAP_SANDBOX_URL => Http::response(namecheapXml(
            '<CommandResponse Type="namecheap.users.getPricing"><UserGetPricingResult><ProductType Name="DOMAIN">'
            .'<ProductCategory Name="REGISTER"><Product Name="com">'
            .'<Price Duration="1" DurationType="YEAR" Price="8.88" RegularPrice="10.98" YourPrice="8.88" CouponPrice="" Currency="USD"/>'
            .'<Price Duration="2" DurationType="YEAR" Price="17.76" RegularPrice="21.96" YourPrice="17.76" CouponPrice="" Currency="USD"/>'
            .'</Product></ProductCategory></ProductType></UserGetPricingResult></CommandResponse>'
        )),
    ]);

    $first = $api->registrationPrice('com', 1);
    $second = $api->registrationPrice('com', 1);

    expect($first)->toBe(['price' => '8.88', 'currency' => 'USD'])
        ->and($second)->toBe($first);
    Http::assertSentCount(1);
    Http::assertSent(static fn (Request $request): bool => $request['Command'] === 'namecheap.users.getPricing'
        && $request['ProductType'] === 'DOMAIN'
        && $request['ActionName'] === 'REGISTER'
        && $request['ProductName'] === 'COM');
});

it('returns domain ownership and expiry from domains.getInfo and null when the domain is not in the account', function (): void {
    $api = namecheapApiFixture();
    Http::fakeSequence(NAMECHEAP_SANDBOX_URL)
        ->push(namecheapXml(
            '<CommandResponse Type="namecheap.domains.getinfo"><DomainGetInfoResult Status="Ok" ID="736542" DomainName="example.com" OwnerName="fixture-user" IsOwner="true" IsPremium="false">'
            .'<DomainDetails><CreatedDate>09/05/2016</CreatedDate><ExpiredDate>09/05/2027</ExpiredDate></DomainDetails></DomainGetInfoResult></CommandResponse>'
        ))
        ->push(namecheapXml('<CommandResponse/>', 'ERROR', '<Errors><Error Number="2019166">Domain not found</Error></Errors>'));

    expect($api->info('example.com'))->toBe([
        'domain' => 'example.com',
        'domain_id' => '736542',
        'is_owner' => true,
        'is_premium' => false,
        'status' => 'Ok',
        'expires_at' => '2027-09-05T00:00:00+00:00',
    ])->and($api->info('missing.com'))->toBeNull();
    Http::assertSent(static fn (Request $request): bool => $request['Command'] === 'namecheap.domains.getInfo'
        && $request['DomainName'] === 'example.com');
});

it('sends every documented required contact role to domains.create and parses the result', function (): void {
    $api = namecheapApiFixture();
    Http::fake([
        NAMECHEAP_SANDBOX_URL => Http::response(namecheapXml(
            '<CommandResponse Type="namecheap.domains.create"><DomainCreateResult Domain="example.com" Registered="true" ChargedAmount="8.8800" DomainID="103877" OrderID="22158" TransactionID="51284" WhoisguardEnable="false" NonRealTimeDomain="false"/></CommandResponse>'
        )),
    ]);

    $result = $api->register('example.com', 2, namecheapContactFixture());

    Http::assertSent(static function (Request $request): bool {
        if ($request['Command'] !== 'namecheap.domains.create' || $request['DomainName'] !== 'example.com' || (string) $request['Years'] !== '2') {
            return false;
        }
        foreach (['Registrant', 'Tech', 'Admin', 'AuxBilling'] as $role) {
            foreach (['FirstName', 'LastName', 'Address1', 'City', 'StateProvince', 'PostalCode', 'Country', 'Phone', 'EmailAddress'] as $field) {
                if (($request[$role.$field] ?? '') === '') {
                    return false;
                }
            }
        }

        return $request['RegistrantPhone'] === '+32.470123456'
            && $request['AuxBillingCountry'] === 'BE'
            && ! isset($request['Address2'])
            && ! isset($request['RegistrantAddress2']);
    });
    expect($result)->toBe([
        'domain' => 'example.com',
        'registered' => true,
        'non_real_time' => false,
        'domain_id' => '103877',
        'order_id' => '22158',
        'transaction_id' => '51284',
        'charged_amount' => '8.8800',
    ]);
});

it('parses the renewed expiry date from domains.renew', function (): void {
    $api = namecheapApiFixture();
    Http::fake([
        NAMECHEAP_SANDBOX_URL => Http::response(namecheapXml(
            '<CommandResponse Type="namecheap.domains.renew"><DomainRenewResult DomainName="example.com" DomainID="151378" Renew="true" OrderID="109116" TransactionID="119569" ChargedAmount="8.8800">'
            .'<DomainDetails><ExpiredDate>4/30/2028 11:31:13 AM</ExpiredDate><NumYears>0</NumYears></DomainDetails></DomainRenewResult></CommandResponse>'
        )),
    ]);

    expect($api->renew('example.com', 1))->toBe([
        'domain' => 'example.com',
        'renewed' => true,
        'domain_id' => '151378',
        'order_id' => '109116',
        'transaction_id' => '119569',
        'charged_amount' => '8.8800',
        'expires_at' => '2028-04-30T00:00:00+00:00',
    ]);
});

it('reports documented Namecheap error numbers without leaking the API key or provider text', function (): void {
    $api = namecheapApiFixture();
    Http::fake([
        NAMECHEAP_SANDBOX_URL => Http::response(namecheapXml(
            '<CommandResponse/>',
            'ERROR',
            '<Errors><Error Number="1011150">Parameter RequestIP is invalid for ApiKey '.NAMECHEAP_FIXTURE_KEY.'</Error></Errors>',
        )),
    ]);

    try {
        $api->check(['example.com']);
        $this->fail('Expected a rejected request.');
    } catch (RuntimeException $exception) {
        $chain = namecheapExceptionChainText($exception);
        expect($exception->getMessage())->toBe('Namecheap Registrar rejected the request (error 1011150).')
            ->and($chain)->not->toContain(NAMECHEAP_FIXTURE_KEY)
            ->and($chain)->not->toContain('RequestIP');
    }
});

it('rejects an HTTP 200 response whose ApiResponse status is not OK or whose root is not ApiResponse', function (string $body): void {
    $api = namecheapApiFixture();
    Http::fake([NAMECHEAP_SANDBOX_URL => Http::response($body)]);

    expect(fn () => $api->check(['example.com']))->toThrow(RuntimeException::class, 'Namecheap Registrar');
})->with([
    'no status' => ['<?xml version="1.0"?><ApiResponse><Errors/><CommandResponse><DomainCheckResult Domain="example.com" Available="true"/></CommandResponse></ApiResponse>'],
    'wrong root' => ['<?xml version="1.0"?><Html Status="OK"><DomainCheckResult Domain="example.com" Available="true"/></Html>'],
    'html' => ['<html><body>maintenance</body></html>'],
]);

it('refuses XML with a DOCTYPE so entity expansion and external entities are never parsed', function (): void {
    $api = namecheapApiFixture();
    $body = '<?xml version="1.0"?><!DOCTYPE ApiResponse [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
        .'<ApiResponse Status="OK"><Errors/><CommandResponse><DomainCheckResult Domain="&x;" Available="true"/></CommandResponse></ApiResponse>';
    Http::fake([NAMECHEAP_SANDBOX_URL => Http::response($body)]);

    expect(fn () => $api->check(['example.com']))->toThrow(RuntimeException::class, 'Namecheap Registrar returned an invalid response.');
});

it('does not follow redirects that could forward the API key to another host', function (): void {
    $api = namecheapApiFixture();
    Http::fake([
        NAMECHEAP_SANDBOX_URL => Http::response('', 307, ['Location' => 'https://attacker.example.test/collect']),
        'https://attacker.example.test/*' => Http::response(namecheapXml('<CommandResponse/>')),
    ]);

    expect(fn () => $api->check(['example.com']))->toThrow(RuntimeException::class, 'Namecheap Registrar returned an unsuccessful response.');
    Http::assertSentCount(1);
    Http::assertNotSent(static fn (Request $request): bool => str_contains($request->url(), 'attacker.example.test'));
});

it('marks billable transport failures as an unknown outcome without leaking secrets', function (): void {
    $api = namecheapApiFixture();
    Http::fake(static fn () => throw new ConnectionException('cURL error 28: timed out ApiKey='.NAMECHEAP_FIXTURE_KEY));

    try {
        $api->register('example.com', 1, namecheapContactFixture());
        $this->fail('Expected an unknown outcome.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Namecheap Registrar request outcome is unknown; reconcile the domain before retrying.')
            ->and(namecheapExceptionChainText($exception))->not->toContain(NAMECHEAP_FIXTURE_KEY);
    }
});

it('refuses an incomplete or non IPv4 configuration before any HTTP request', function (array $overrides): void {
    $api = namecheapApiFixture($overrides);
    Http::fake();

    expect(fn () => $api->check(['example.com']))->toThrow(RuntimeException::class, 'Namecheap Registrar is not configured.');
    Http::assertNothingSent();
})->with([
    'missing key' => [['api_key' => '']],
    'ipv6 client ip' => [['client_ip' => '2001:db8::1']],
    'hostname client ip' => [['client_ip' => 'example.test']],
]);

it('uses the production endpoint only when sandbox is disabled', function (): void {
    $api = namecheapApiFixture(['sandbox' => false]);
    Http::fake(['https://api.namecheap.com/xml.response' => Http::response(namecheapXml(
        '<CommandResponse Type="namecheap.domains.check"><DomainCheckResult Domain="example.com" Available="false" ErrorNo="0" IsPremiumName="false"/></CommandResponse>'
    ))]);

    $api->check(['example.com']);

    Http::assertSent(static fn (Request $request): bool => $request->url() === 'https://api.namecheap.com/xml.response');
});

it('refuses every Namecheap HTTP action in the demo environment', function (): void {
    $api = namecheapApiFixture();
    app()['env'] = 'demo';
    Http::fake();

    foreach ([
        fn () => $api->check(['example.com']),
        fn () => $api->registrationPrice('com', 1),
        fn () => $api->info('example.com'),
        fn () => $api->register('example.com', 1, namecheapContactFixture()),
        fn () => $api->renew('example.com', 1),
        fn () => $api->nameservers('example', 'com'),
        fn () => $api->hosts('example', 'com'),
        fn () => $api->setHosts('example', 'com', [], null),
    ] as $action) {
        expect($action)->toThrow(RuntimeException::class, 'disabled in the demo environment');
    }

    Http::assertNothingSent();
});

it('parses domains.dns.getList and domains.dns.getHosts including the live lowercase host elements', function (): void {
    $api = namecheapApiFixture();
    Http::fakeSequence(NAMECHEAP_SANDBOX_URL)
        ->push(namecheapXml(
            '<CommandResponse Type="namecheap.domains.dns.getList"><DomainDNSGetListResult Domain="example.com" IsUsingOurDNS="true">'
            .'<Nameserver>dns1.registrar-servers.com</Nameserver><Nameserver>dns2.registrar-servers.com</Nameserver></DomainDNSGetListResult></CommandResponse>'
        ))
        ->push(namecheapXml(
            '<CommandResponse Type="namecheap.domains.dns.getHosts"><DomainDNSGetHostsResult Domain="example.com" EmailType="MX" IsUsingOurDNS="true">'
            .'<host HostId="12" Name="@" Type="A" Address="203.0.113.10" MXPref="10" TTL="300" IsActive="true"/>'
            .'<Host HostId="14" Name="@" Type="MX" Address="mx1.mail.example.net." MXPref="5" TTL="3600"/>'
            .'</DomainDNSGetHostsResult></CommandResponse>'
        ));

    expect($api->nameservers('example', 'com'))->toBe([
        'domain' => 'example.com',
        'using_namecheap_dns' => true,
        'nameservers' => ['dns1.registrar-servers.com', 'dns2.registrar-servers.com'],
    ])->and($api->hosts('example', 'com'))->toBe([
        'domain' => 'example.com',
        'using_namecheap_dns' => true,
        'email_type' => 'MX',
        'hosts' => [
            ['name' => '@', 'type' => 'A', 'address' => '203.0.113.10', 'mx_pref' => 10, 'ttl' => 300],
            ['name' => '@', 'type' => 'MX', 'address' => 'mx1.mail.example.net.', 'mx_pref' => 5, 'ttl' => 3600],
        ],
    ]);
    Http::assertSent(static fn (Request $request): bool => $request['Command'] === 'namecheap.domains.dns.getHosts'
        && $request['SLD'] === 'example'
        && $request['TLD'] === 'com');
});

it('sends the complete numbered host list to domains.dns.setHosts and requires IsSuccess', function (): void {
    $api = namecheapApiFixture();
    Http::fakeSequence(NAMECHEAP_SANDBOX_URL)
        ->push(namecheapXml('<CommandResponse Type="namecheap.domains.dns.setHosts"><DomainDNSSetHostsResult Domain="example.com" IsSuccess="true"/></CommandResponse>'))
        ->push(namecheapXml('<CommandResponse Type="namecheap.domains.dns.setHosts"><DomainDNSSetHostsResult Domain="example.com" IsSuccess="false"/></CommandResponse>'));
    $hosts = [
        ['name' => '@', 'type' => 'A', 'address' => '203.0.113.10', 'mx_pref' => 10, 'ttl' => 300],
        ['name' => '@', 'type' => 'MX', 'address' => 'mx1.mail.example.net.', 'mx_pref' => 5, 'ttl' => null],
    ];

    $api->setHosts('example', 'com', $hosts, 'MX');

    Http::assertSent(static fn (Request $request): bool => $request['Command'] === 'namecheap.domains.dns.setHosts'
        && $request['SLD'] === 'example'
        && $request['TLD'] === 'com'
        && $request['EmailType'] === 'MX'
        && $request['HostName1'] === '@' && $request['RecordType1'] === 'A' && $request['Address1'] === '203.0.113.10' && (string) $request['TTL1'] === '300'
        && ! isset($request['MXPref1'])
        && $request['RecordType2'] === 'MX' && (string) $request['MXPref2'] === '5' && ! isset($request['TTL2'])
        && ! isset($request['HostName3']));
    expect(fn () => $api->setHosts('example', 'com', $hosts, 'MX'))
        ->toThrow(RuntimeException::class, 'did not confirm the DNS host update');
});
