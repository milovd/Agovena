<?php

declare(strict_types=1);

use Agovena\Modules\Domains\Contracts\DomainDnsProvider;
use Agovena\Modules\Domains\DomainDnsProviderRegistry;
use Agovena\Modules\Domains\Http\Livewire\Customer\DomainShow;
use Agovena\Modules\Domains\Models\DomainRegistration;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function (): void {
    installAndEnableModules(['domains']);
});

function recordingDnsProvider(): DomainDnsProvider
{
    return new class implements DomainDnsProvider
    {
        /** @var list<array<string, mixed>> */
        public array $saved = [];

        public function key(): string
        {
            return 'recording-dns';
        }

        public function capabilities(): array
        {
            return ['zone_management', 'records'];
        }

        public function ensureZone(DomainRegistration $registration): array
        {
            return ['zone_reference' => 'zone-1', 'nameservers' => [], 'status' => 'active', 'meta' => []];
        }

        public function listRecords(DomainRegistration $registration): array
        {
            return [['id' => "rec');alert(1);//", 'type' => 'MX', 'name' => 'example.test', 'content' => 'mail.example.test', 'ttl' => 3600, 'priority' => 20]];
        }

        public function upsertRecord(DomainRegistration $registration, array $record): array
        {
            if (($record['content'] ?? '') === 'not-an-ip') {
                throw new InvalidArgumentException('An IPv4 address is required for an A record.');
            }
            $this->saved[] = $record;

            return ['id' => 'rec-new'] + $record;
        }

        public function deleteRecord(DomainRegistration $registration, string $recordReference): array
        {
            return ['id' => $recordReference];
        }
    };
}

/** @return array{0: User, 1: DomainRegistration, 2: DomainDnsProvider} */
function customerDomainWithDns(): array
{
    $provider = recordingDnsProvider();
    app(DomainDnsProviderRegistry::class)->register($provider);
    $user = User::factory()->create();
    $customer = $user->ensureCustomer();
    $registration = DomainRegistration::query()->create([
        'number' => 'DOM-DNS-'.uniqid(),
        'customer_id' => $customer->id,
        'domain_name' => 'example.test',
        'dns_provider_key' => 'recording-dns',
        'status' => 'active',
    ]);

    return [$user, $registration, $provider];
}

it('sends the MX priority from the customer DNS form to the provider', function (): void {
    [$user, $registration, $provider] = customerDomainWithDns();

    Livewire::actingAs($user)
        ->test(DomainShow::class, ['registration' => $registration])
        ->set('recordType', 'MX')
        ->set('recordName', '@')
        ->set('recordContent', 'mail.example.test')
        ->set('recordPriority', 20)
        ->call('saveRecord')
        ->assertHasNoErrors();

    expect($provider->saved)->toHaveCount(1)
        ->and($provider->saved[0]['type'])->toBe('MX')
        ->and($provider->saved[0]['priority'])->toBe(20);
});

it('rejects an MX record without a valid priority before calling the provider', function (): void {
    [$user, $registration, $provider] = customerDomainWithDns();

    Livewire::actingAs($user)
        ->test(DomainShow::class, ['registration' => $registration])
        ->set('recordType', 'MX')
        ->set('recordContent', 'mail.example.test')
        ->set('recordPriority', 70000)
        ->call('saveRecord')
        ->assertHasErrors(['recordPriority']);

    expect($provider->saved)->toBe([]);
});

it('does not send a priority for record types that do not use one', function (): void {
    [$user, $registration, $provider] = customerDomainWithDns();

    Livewire::actingAs($user)
        ->test(DomainShow::class, ['registration' => $registration])
        ->set('recordType', 'A')
        ->set('recordContent', '203.0.113.10')
        ->call('saveRecord')
        ->assertHasNoErrors();

    expect($provider->saved[0])->not->toHaveKey('priority');
});

it('renders provider record ids safely and without demo-only copy', function (): void {
    [$user, $registration] = customerDomainWithDns();

    $html = Livewire::actingAs($user)
        ->test(DomainShow::class, ['registration' => $registration])
        ->html();

    expect($html)->not->toContain("deleteRecord('rec');alert(1);//')")
        ->and($html)->not->toContain('demo zone');
});

it('refuses another customer access to the DNS screen', function (): void {
    [, $registration] = customerDomainWithDns();
    $intruder = User::factory()->create();

    Livewire::actingAs($intruder)
        ->test(DomainShow::class, ['registration' => $registration])
        ->assertStatus(404);
});

it('shows a validation error instead of failing when the provider refuses a record', function (): void {
    [$user, $registration, $provider] = customerDomainWithDns();

    Livewire::actingAs($user)
        ->test(DomainShow::class, ['registration' => $registration])
        ->set('recordType', 'A')
        ->set('recordContent', 'not-an-ip')
        ->call('saveRecord')
        ->assertHasErrors(['recordContent'])
        ->assertSee(__('domains::customer.record_rejected'));

    expect($provider->saved)->toBe([]);
});
