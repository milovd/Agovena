<?php

declare(strict_types=1);

use Agovena\Extensions\NamecheapDomain\NamecheapApi;
use Agovena\Extensions\NamecheapDomain\NamecheapDnsApi;
use Agovena\Extensions\NamecheapDomain\NamecheapDnsProvider;
use Agovena\Extensions\NamecheapDomain\NamecheapOperationNotSupported;
use Agovena\Extensions\NamecheapDomain\NamecheapRegistrar;
use Agovena\Modules\Domains\Contracts\RefreshesRegistrationStatus;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\DomainRegistrarRegistry;
use Agovena\Modules\Domains\DomainService;
use Agovena\Modules\Domains\Enums\DomainRegistrationStatus;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    installAndEnableModules(['domains']);
    app(ExtensionManager::class)->discover();
});

/** @return NamecheapApi&object */
function namecheapFakeApi(): NamecheapApi
{
    return new class implements NamecheapApi
    {
        /** @var list<array{0: string, 1: array<int, mixed>}> */
        public array $calls = [];

        /** @var array<string, mixed> */
        public array $checkResult = ['domains' => []];

        public ?Throwable $checkException = null;

        /** @var array{price: string, currency: string}|null */
        public ?array $price = null;

        /** @var list<array<string, mixed>|null> */
        public array $infos = [];

        /** @var array<string, mixed> */
        public array $registerResult = [];

        /** @var array<string, mixed> */
        public array $renewResult = [];

        public function check(array $domains): array
        {
            $this->calls[] = ['check', [$domains]];
            if ($this->checkException !== null) {
                throw $this->checkException;
            }

            return $this->checkResult;
        }

        public function registrationPrice(string $tld, int $years): ?array
        {
            $this->calls[] = ['registrationPrice', [$tld, $years]];

            return $this->price;
        }

        public function info(string $domain): ?array
        {
            $this->calls[] = ['info', [$domain]];

            return array_shift($this->infos);
        }

        public function register(string $domain, int $years, array $contact): array
        {
            $this->calls[] = ['register', [$domain, $years, $contact]];

            return $this->registerResult;
        }

        public function renew(string $domain, int $years): array
        {
            $this->calls[] = ['renew', [$domain, $years]];

            return $this->renewResult;
        }

        /** @return list<string> */
        public function callNames(): array
        {
            return array_map(static fn (array $call): string => $call[0], $this->calls);
        }
    };
}

function namecheapReadinessRegistration(array $attributes = [], bool $withContact = true, array $addressOverrides = []): DomainRegistration
{
    installAndEnableModules(['domains']);
    $customerId = null;
    $email = null;
    if ($withContact) {
        $customer = Customer::factory()->create(['name' => 'Jane Doe', 'email' => 'jane@example.test']);
        CustomerAddress::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'name' => 'Jane Doe',
            'company' => 'Fixture BV',
            'line1' => 'Fixture Street 1',
            'line2' => null,
            'city' => 'Ghent',
            'region' => 'Oost-Vlaanderen',
            'postal_code' => '9000',
            'country' => 'be',
            'phone' => '+32 470123456',
            'is_default_billing' => true,
        ], $addressOverrides));
        $customerId = $customer->id;
        $email = 'jane@example.test';
    }

    return DomainRegistration::query()->create(array_merge([
        'number' => 'DOM-'.strtoupper(bin2hex(random_bytes(4))),
        'customer_id' => $customerId,
        'customer_email' => $email,
        'customer_name' => 'Jane Doe',
        'domain_name' => 'example.com',
        'status' => DomainRegistrationStatus::Pending,
        'provider_key' => 'namecheap-registrar',
        'registrar_key' => 'namecheap-registrar',
        'meta' => ['provider_settings' => ['years' => 2]],
    ], $attributes));
}

function namecheapOwnedInfo(string $expiresAt, bool $premium = false, bool $owner = true): array
{
    return [
        'domain' => 'example.com',
        'domain_id' => '103877',
        'is_owner' => $owner,
        'is_premium' => $premium,
        'status' => 'Ok',
        'expires_at' => $expiresAt,
    ];
}

function namecheapCheckEntry(array $overrides = []): array
{
    return ['domains' => [array_merge([
        'domain' => 'example.com',
        'available' => true,
        'premium' => false,
        'error_no' => '0',
        'premium_registration_price' => null,
        'eap_fee' => null,
        'icann_fee' => null,
    ], $overrides)]];
}

it('quotes an available regular domain from the account REGISTER price', function (): void {
    $api = namecheapFakeApi();
    $api->checkResult = namecheapCheckEntry();
    $api->price = ['price' => '8.88', 'currency' => 'USD'];

    expect((new NamecheapRegistrar($api))->checkAvailability('Example.COM'))->toBe([
        'available' => true,
        'domain' => 'example.com',
        'price_minor' => 888,
        'currency' => 'USD',
        'reason' => null,
    ])->and($api->calls)->toBe([['check', [['example.com']]], ['registrationPrice', ['com', 1]]]);
});

it('refuses premium, EAP, unavailable and provider-error results instead of selling them at a regular price', function (array $entry, string $reason): void {
    $api = namecheapFakeApi();
    $api->checkResult = namecheapCheckEntry($entry);
    $api->price = ['price' => '8.88', 'currency' => 'USD'];

    $result = (new NamecheapRegistrar($api))->checkAvailability('example.com');

    expect($result['available'])->toBeFalse()
        ->and($result['price_minor'])->toBeNull()
        ->and($result['reason'])->toBe($reason)
        ->and($api->callNames())->toBe(['check']);
})->with([
    'premium' => [['premium' => true, 'premium_registration_price' => '13000.0000'], 'premium_not_supported'],
    'eap' => [['eap_fee' => '150.0000'], 'premium_not_supported'],
    'taken' => [['available' => false], 'unavailable'],
    'provider error' => [['error_no' => '3031510'], 'provider_error'],
]);

it('marks an available domain without an account price as unpriced', function (): void {
    $api = namecheapFakeApi();
    $api->checkResult = namecheapCheckEntry();

    expect((new NamecheapRegistrar($api))->checkAvailability('example.com'))->toMatchArray([
        'available' => true,
        'price_minor' => null,
        'currency' => null,
        'reason' => 'price_unavailable',
    ]);
});

it('explicitly refuses TLDs that need extended attributes and IDN names without calling Namecheap', function (string $domain, string $reason): void {
    $api = namecheapFakeApi();
    $registrar = new NamecheapRegistrar($api);

    expect($registrar->checkAvailability($domain))->toMatchArray(['available' => false, 'reason' => $reason])
        ->and($api->calls)->toBe([]);

    $registration = namecheapReadinessRegistration(['domain_name' => $domain]);
    expect(fn () => $registrar->register($registration))->toThrow(RuntimeException::class, 'not supported')
        ->and($api->calls)->toBe([]);
})->with([
    'eu' => ['example.eu', 'tld_not_supported'],
    'de' => ['example.de', 'tld_not_supported'],
    'co.uk' => ['example.co.uk', 'tld_not_supported'],
    'com.au' => ['example.com.au', 'tld_not_supported'],
    'idn' => ['xn--bcher-kva.com', 'idn_not_supported'],
]);

it('returns an unavailable result instead of throwing when the availability call fails', function (): void {
    $api = namecheapFakeApi();
    $api->checkException = new RuntimeException('Namecheap Registrar request failed.');

    expect((new NamecheapRegistrar($api))->checkAvailability('example.com'))->toBe([
        'available' => false,
        'domain' => 'example.com',
        'price_minor' => null,
        'currency' => null,
        'reason' => 'provider_unavailable',
    ]);
});

it('registers with the customer billing contact for all roles and records the provider expiry', function (): void {
    $api = namecheapFakeApi();
    $api->infos = [null, namecheapOwnedInfo('2028-10-05T00:00:00+00:00')];
    $api->checkResult = namecheapCheckEntry();
    $api->registerResult = [
        'domain' => 'example.com',
        'registered' => true,
        'non_real_time' => false,
        'domain_id' => '103877',
        'order_id' => '22158',
        'transaction_id' => '51284',
        'charged_amount' => '17.7600',
    ];
    $registration = namecheapReadinessRegistration();

    $result = (new NamecheapRegistrar($api))->register($registration);

    expect($api->callNames())->toBe(['info', 'check', 'register', 'info'])
        ->and($api->calls[2][1])->toBe(['example.com', 2, [
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
        ]])
        ->and($result)->toBe([
            'provider_reference' => '103877',
            'expires_at' => '2028-10-05T00:00:00+00:00',
            'status' => 'active',
            'meta' => [
                'domain' => 'example.com',
                'domain_id' => '103877',
                'order_id' => '22158',
                'transaction_id' => '51284',
                'charged_amount' => '17.7600',
            ],
        ]);
});

it('refuses registration before any billable call when the registrant contact is incomplete', function (array $attributes, bool $withContact, array $address, string $field): void {
    $api = namecheapFakeApi();
    $registration = namecheapReadinessRegistration($attributes, $withContact, $address);

    try {
        (new NamecheapRegistrar($api))->register($registration);
        $this->fail('Expected the registration to be refused.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('registrant contact')
            ->and($exception->getMessage())->toContain($field)
            ->and($exception->getMessage())->not->toContain('Fixture Street');
    }

    expect($api->calls)->toBe([]);
})->with([
    'no customer' => [[], false, [], 'address'],
    'no region' => [[], true, ['region' => null], 'region'],
    'single name' => [[], true, ['name' => 'Jane'], 'name'],
    'local phone' => [[], true, ['phone' => '0470123456'], 'phone'],
    'missing phone' => [[], true, ['phone' => null], 'phone'],
    'bad country' => [[], true, ['country' => 'B1'], 'country'],
]);

it('reconciles a registration that already succeeded instead of registering twice', function (): void {
    $api = namecheapFakeApi();
    $api->infos = [namecheapOwnedInfo('2028-10-05T00:00:00+00:00')];
    $registration = namecheapReadinessRegistration();

    $result = (new NamecheapRegistrar($api))->register($registration);

    expect($api->callNames())->toBe(['info'])
        ->and($result)->toMatchArray([
            'provider_reference' => '103877',
            'expires_at' => '2028-10-05T00:00:00+00:00',
            'status' => 'active',
        ])
        ->and($result['meta']['reconciled'] ?? null)->toBeTrue();
});

it('does not report a non real time or rejected registration as active', function (array $flags, string $status): void {
    $api = namecheapFakeApi();
    $api->infos = [null, null];
    $api->checkResult = namecheapCheckEntry();
    $api->registerResult = array_merge([
        'domain' => 'example.com',
        'registered' => true,
        'non_real_time' => false,
        'domain_id' => '103877',
        'order_id' => null,
        'transaction_id' => null,
        'charged_amount' => null,
    ], $flags);

    expect((new NamecheapRegistrar($api))->register(namecheapReadinessRegistration())['status'])->toBe($status);
})->with([
    'non real time' => [['non_real_time' => true], 'registering'],
    'not registered' => [['registered' => false], 'failed'],
    'other domain' => [['domain' => 'other.com'], 'failed'],
]);

it('renews when the provider expiry equals the recorded expiry and records the new expiry', function (): void {
    $api = namecheapFakeApi();
    $api->infos = [namecheapOwnedInfo('2027-09-05T00:00:00+00:00')];
    $api->renewResult = [
        'domain' => 'example.com',
        'renewed' => true,
        'domain_id' => '103877',
        'order_id' => '109116',
        'transaction_id' => '119569',
        'charged_amount' => '8.8800',
        'expires_at' => '2028-09-05T00:00:00+00:00',
    ];
    $registration = namecheapReadinessRegistration(['expires_at' => '2027-09-05 00:00:00', 'status' => DomainRegistrationStatus::Active]);

    $result = (new NamecheapRegistrar($api))->renew($registration, 1);

    expect($api->calls)->toBe([['info', ['example.com']], ['renew', ['example.com', 1]]])
        ->and($result)->toMatchArray([
            'provider_reference' => '103877',
            'expires_at' => '2028-09-05T00:00:00+00:00',
            'status' => 'active',
        ]);
});

it('reconciles a renewal whose response was lost instead of charging again', function (): void {
    $api = namecheapFakeApi();
    $api->infos = [namecheapOwnedInfo('2028-09-06T00:00:00+00:00')];
    $registration = namecheapReadinessRegistration(['expires_at' => '2027-09-05 00:00:00', 'status' => DomainRegistrationStatus::Failed]);

    $result = (new NamecheapRegistrar($api))->renew($registration, 1);

    expect($api->callNames())->toBe(['info'])
        ->and($result)->toMatchArray(['expires_at' => '2028-09-06T00:00:00+00:00', 'status' => 'active'])
        ->and($result['meta']['reconciled'] ?? null)->toBeTrue();
});

it('refuses renewal before any billable call when idempotency cannot be proven', function (?array $info, ?string $localExpiry, string $message): void {
    $api = namecheapFakeApi();
    $api->infos = [$info];
    $registration = namecheapReadinessRegistration(['expires_at' => $localExpiry, 'status' => DomainRegistrationStatus::Active]);

    expect(fn () => (new NamecheapRegistrar($api))->renew($registration, 1))->toThrow(RuntimeException::class, $message)
        ->and($api->callNames())->not->toContain('renew');
})->with([
    'not in account' => [null, '2027-09-05 00:00:00', 'not in this Namecheap account'],
    'owned by someone else' => [namecheapOwnedInfo('2027-09-05T00:00:00+00:00', owner: false), '2027-09-05 00:00:00', 'not in this Namecheap account'],
    'premium' => [namecheapOwnedInfo('2027-09-05T00:00:00+00:00', premium: true), '2027-09-05 00:00:00', 'Premium'],
    'unknown local expiry' => [namecheapOwnedInfo('2027-09-05T00:00:00+00:00'), null, 'known expiry'],
    'expiry mismatch' => [namecheapOwnedInfo('2027-12-01T00:00:00+00:00'), '2027-09-05 00:00:00', 'does not match'],
]);

it('advertises only the operations it implements', function (): void {
    expect((new NamecheapRegistrar(namecheapFakeApi()))->capabilities())
        ->toBe(['availability_check', 'registration', 'renewal'])
        ->and((new NamecheapDnsProvider(namecheapFakeDnsApi()))->capabilities())
        ->toBe(['zone_management', 'records']);
});

it('refuses to register when the real-time check does not confirm a regular available domain', function (array $entry): void {
    $api = namecheapFakeApi();
    $api->infos = [null];
    $api->checkResult = namecheapCheckEntry($entry);

    expect(fn () => (new NamecheapRegistrar($api))->register(namecheapReadinessRegistration()))
        ->toThrow(RuntimeException::class, 'cannot be registered')
        ->and($api->callNames())->toBe(['info', 'check']);
})->with([
    'taken' => [['available' => false]],
    'premium' => [['premium' => true, 'premium_registration_price' => '13000.0000']],
    'provider error' => [['error_no' => '3031510']],
]);

it('refreshes a pending registration from domains.getInfo without any billable call', function (): void {
    $api = namecheapFakeApi();
    $api->infos = [null, namecheapOwnedInfo('2028-10-05T00:00:00+00:00')];
    $registration = namecheapReadinessRegistration([
        'status' => DomainRegistrationStatus::Registering,
        'provider_reference' => '103877',
        'meta' => ['provider_response' => ['order_id' => '22158', 'non_real_time' => true]],
    ]);
    $registrar = new NamecheapRegistrar($api);

    expect($registrar)->toBeInstanceOf(RefreshesRegistrationStatus::class)
        ->and($registrar->refreshRegistration($registration))->toBe([
            'provider_reference' => '103877',
            'expires_at' => null,
            'status' => 'registering',
            'meta' => ['order_id' => '22158', 'non_real_time' => true],
        ])
        ->and($registrar->refreshRegistration($registration))->toMatchArray([
            'provider_reference' => '103877',
            'expires_at' => '2028-10-05T00:00:00+00:00',
            'status' => 'active',
        ])
        ->and($api->callNames())->toBe(['info', 'info']);
});

/** @return NamecheapDnsApi&object */
function namecheapFakeDnsApi(array $hosts = [], ?string $emailType = 'MX', bool $usingNamecheapDns = true): NamecheapDnsApi
{
    return new class($hosts, $emailType, $usingNamecheapDns) implements NamecheapDnsApi
    {
        /** @var list<array{0: string, 1: list<array<string, mixed>>, 2: string|null}> */
        public array $writes = [];

        public ?bool $lockFreeDuringWrite = null;

        public function __construct(
            public array $hosts,
            public ?string $emailType,
            public bool $usingNamecheapDns,
        ) {}

        public function nameservers(string $sld, string $tld): array
        {
            return [
                'domain' => $sld.'.'.$tld,
                'using_namecheap_dns' => $this->usingNamecheapDns,
                'nameservers' => ['dns1.registrar-servers.com', 'dns2.registrar-servers.com'],
            ];
        }

        public function hosts(string $sld, string $tld): array
        {
            return [
                'domain' => $sld.'.'.$tld,
                'using_namecheap_dns' => $this->usingNamecheapDns,
                'email_type' => $this->emailType,
                'hosts' => $this->hosts,
            ];
        }

        public function setHosts(string $sld, string $tld, array $hosts, ?string $emailType): void
        {
            $lock = Cache::lock('namecheap-domain:dns:'.$sld.'.'.$tld, 5);
            $this->lockFreeDuringWrite = $lock->get();
            if ($this->lockFreeDuringWrite) {
                $lock->release();
            }
            $this->writes[] = [$sld.'.'.$tld, $hosts, $emailType];
            $this->hosts = $hosts;
            $this->emailType = $emailType;
        }
    };
}

function namecheapHost(string $name, string $type, string $address, ?int $mxPref = 10, ?int $ttl = 1800): array
{
    return ['name' => $name, 'type' => $type, 'address' => $address, 'mx_pref' => $mxPref, 'ttl' => $ttl];
}

function namecheapDnsRegistration(): DomainRegistration
{
    return new DomainRegistration(['domain_name' => 'example.com', 'meta' => []]);
}

it('confirms Namecheap BasicDNS as the zone and refuses domains delegated elsewhere', function (): void {
    expect((new NamecheapDnsProvider(namecheapFakeDnsApi()))->ensureZone(namecheapDnsRegistration()))->toBe([
        'zone_reference' => 'example.com',
        'nameservers' => ['dns1.registrar-servers.com', 'dns2.registrar-servers.com'],
        'status' => 'active',
        'meta' => ['domain' => 'example.com'],
    ]);

    expect(fn () => (new NamecheapDnsProvider(namecheapFakeDnsApi(usingNamecheapDns: false)))->ensureZone(namecheapDnsRegistration()))
        ->toThrow(NamecheapOperationNotSupported::class, 'changing nameservers is not supported');
});

it('adds a record by writing back every existing host with its MX priority and TTL under a per-domain lock', function (): void {
    $existing = [
        namecheapHost('@', 'A', '203.0.113.10', 10, 300),
        namecheapHost('@', 'MX', 'mx1.mail.example.net.', 5, 3600),
        namecheapHost('@', 'CAA', '0 issue "letsencrypt.org"', 10, 1800),
        namecheapHost('old', 'URL301', 'https://example.org/', 10, null),
    ];
    $api = namecheapFakeDnsApi($existing);

    $created = (new NamecheapDnsProvider($api))->upsertRecord(namecheapDnsRegistration(), [
        'type' => 'MX',
        'name' => 'example.com',
        'content' => 'mx2.mail.example.net.',
        'ttl' => 3600,
        'priority' => 20,
    ]);

    expect($api->writes)->toBe([['example.com', [...$existing, namecheapHost('@', 'MX', 'mx2.mail.example.net.', 20, 3600)], 'MX']])
        ->and($api->lockFreeDuringWrite)->toBeFalse()
        ->and($created)->toMatchArray(['type' => 'MX', 'name' => '@', 'content' => 'mx2.mail.example.net.', 'ttl' => 3600, 'priority' => 20])
        ->and(Cache::lock('namecheap-domain:dns:example.com', 5)->get())->toBeTrue();
});

it('lists records with content references and updates or deletes only the referenced host', function (): void {
    $api = namecheapFakeDnsApi([
        namecheapHost('@', 'A', '203.0.113.10', 10, 300),
        namecheapHost('www', 'CNAME', 'example.com.', 10, 1800),
        namecheapHost('@', 'TXT', 'v=spf1 -all', 10, 1800),
    ]);
    $provider = new NamecheapDnsProvider($api);
    $records = $provider->listRecords(namecheapDnsRegistration());

    expect(array_map(static fn (array $record): array => array_diff_key($record, ['id' => true]), $records))->toBe([
        ['type' => 'A', 'name' => '@', 'content' => '203.0.113.10', 'ttl' => 300],
        ['type' => 'CNAME', 'name' => 'www', 'content' => 'example.com.', 'ttl' => 1800],
        ['type' => 'TXT', 'name' => '@', 'content' => 'v=spf1 -all', 'ttl' => 1800],
    ])->and(count(array_unique(array_column($records, 'id'))))->toBe(3);

    $provider->upsertRecord(namecheapDnsRegistration(), ['id' => $records[0]['id'], 'type' => 'A', 'name' => '@', 'content' => '203.0.113.20', 'ttl' => 600]);
    $provider->deleteRecord(namecheapDnsRegistration(), $records[2]['id']);

    expect($api->hosts)->toBe([
        namecheapHost('@', 'A', '203.0.113.20', null, 600),
        namecheapHost('www', 'CNAME', 'example.com.', 10, 1800),
    ])->and($api->writes[1][2])->toBe('MX');

    expect(fn () => $provider->deleteRecord(namecheapDnsRegistration(), $records[0]['id']))
        ->toThrow(RuntimeException::class, 'no longer exists')
        ->and($api->writes)->toHaveCount(2);
});

it('refuses DNS changes that Namecheap would not serve or that would replace its mail setup', function (Closure $makeApi, array $record, string $exception, string $message): void {
    $api = $makeApi();

    expect(fn () => (new NamecheapDnsProvider($api))->upsertRecord(namecheapDnsRegistration(), $record))
        ->toThrow($exception, $message)
        ->and($api->writes)->toBe([]);
})->with([
    'not on BasicDNS' => [fn () => namecheapFakeDnsApi(usingNamecheapDns: false), ['type' => 'A', 'name' => '@', 'content' => '203.0.113.10', 'ttl' => 300], NamecheapOperationNotSupported::class, 'host records cannot be changed'],
    'email forwarding' => [fn () => namecheapFakeDnsApi(emailType: 'FWD'), ['type' => 'MX', 'name' => '@', 'content' => 'mx.example.net.', 'ttl' => 300, 'priority' => 10], NamecheapOperationNotSupported::class, 'custom MX records are not supported'],
    'ttl above namecheap maximum' => [fn () => namecheapFakeDnsApi(), ['type' => 'A', 'name' => '@', 'content' => '203.0.113.10', 'ttl' => 86400], InvalidArgumentException::class, 'between 60 and 60000'],
    'mx without priority' => [fn () => namecheapFakeDnsApi(), ['type' => 'MX', 'name' => '@', 'content' => 'mx.example.net.', 'ttl' => 300], InvalidArgumentException::class, 'priority'],
    'unsupported type' => [fn () => namecheapFakeDnsApi(), ['type' => 'URL', 'name' => '@', 'content' => 'https://example.org/', 'ttl' => 300], InvalidArgumentException::class, 'not supported'],
]);

it('registers the Namecheap DNS provider with the extension', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-domain');

    expect(app(DomainDnsProviderRegistry::class)->get('namecheap-dns'))->toBeInstanceOf(NamecheapDnsProvider::class);
});

it('registers and safely retries a lost renewal end to end through the domain service', function (): void {
    installAndEnableModules(['domains']);
    installAndEnableExtension('namecheap-domain');
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('namecheap-domain', 'api_user', 'fixture-user');
    $settings->set('namecheap-domain', 'api_key', 'fixture-secret-key-0002', secret: true);
    $settings->set('namecheap-domain', 'username', 'fixture-user');
    $settings->set('namecheap-domain', 'client_ip', '198.51.100.10');
    $settings->set('namecheap-domain', 'sandbox', true);
    expect(app(DomainRegistrarRegistry::class)->get('namecheap-registrar'))->toBeInstanceOf(NamecheapRegistrar::class);

    $xml = static fn (string $body, string $status = 'OK', string $errors = '<Errors/>'): string => '<?xml version="1.0" encoding="utf-8"?><ApiResponse xmlns="http://api.namecheap.com/xml.response" Status="'.$status.'">'.$errors.$body.'</ApiResponse>';
    $info = static fn (string $date): string => $xml('<CommandResponse Type="namecheap.domains.getinfo"><DomainGetInfoResult Status="Ok" ID="103877" DomainName="example.com" OwnerName="fixture-user" IsOwner="true" IsPremium="false"><DomainDetails><CreatedDate>10/05/2026</CreatedDate><ExpiredDate>'.$date.'</ExpiredDate></DomainDetails></DomainGetInfoResult></CommandResponse>');
    $responses = [
        $xml('<CommandResponse/>', 'ERROR', '<Errors><Error Number="2019166">Domain not found</Error></Errors>'),
        $xml('<CommandResponse Type="namecheap.domains.check"><DomainCheckResult Domain="example.com" Available="true" ErrorNo="0" Description="" IsPremiumName="false" PremiumRegistrationPrice="0" PremiumRenewalPrice="0" PremiumRestorePrice="0" PremiumTransferPrice="0" IcannFee="0" EapFee="0"/></CommandResponse>'),
        $xml('<CommandResponse Type="namecheap.domains.create"><DomainCreateResult Domain="example.com" Registered="true" ChargedAmount="17.7600" DomainID="103877" OrderID="22158" TransactionID="51284" NonRealTimeDomain="false"/></CommandResponse>'),
        $info('10/05/2028'),
        $info('10/05/2028'),
        'timeout',
        $info('10/05/2029'),
    ];
    $commands = [];
    Http::fake(static function (Request $request) use (&$responses, &$commands) {
        $commands[] = $request['Command'];
        $next = array_shift($responses);
        if ($next === 'timeout') {
            throw new ConnectionException('cURL error 28: timed out');
        }

        return Http::response($next);
    });

    $service = app(DomainService::class);
    $registration = $service->register(namecheapReadinessRegistration());

    expect($registration->status)->toBe(DomainRegistrationStatus::Active)
        ->and($registration->provider_reference)->toBe('103877')
        ->and($registration->expires_at?->toDateString())->toBe('2028-10-05');

    expect(fn () => $service->renew($registration, 1))->toThrow(RuntimeException::class, 'outcome is unknown');
    $retried = $service->renew($registration->fresh(), 1);

    expect($retried->status)->toBe(DomainRegistrationStatus::Active)
        ->and($retried->expires_at?->toDateString())->toBe('2029-10-05')
        ->and($commands)->toBe([
            'namecheap.domains.getInfo',
            'namecheap.domains.check',
            'namecheap.domains.create',
            'namecheap.domains.getInfo',
            'namecheap.domains.getInfo',
            'namecheap.domains.renew',
            'namecheap.domains.getInfo',
        ]);
});
