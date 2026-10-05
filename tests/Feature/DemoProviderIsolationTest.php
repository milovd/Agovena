<?php

declare(strict_types=1);

use Agovena\Extensions\CloudflareDomain\HttpCloudflareApi;
use Agovena\Extensions\CloudflareDomain\HttpCloudflareDnsApi;
use Agovena\Extensions\Pterodactyl\HttpPterodactylApi;
use Agovena\Extensions\Pterodactyl\PterodactylProviderException;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    app(ExtensionManager::class)->discover();
    app()['env'] = 'demo';
    Http::fake();
    Http::preventStrayRequests();
});

test('demo refuses every Pterodactyl HTTP action even with configured credentials', function (): void {
    $api = new HttpPterodactylApi(app(ExtensionSettingsRepository::class), [
        'panel_url' => 'https://panel.example.test',
        'application_api_key' => 'not-a-real-key',
        'client_api_key' => 'not-a-real-key',
        'user_id' => '1',
    ]);

    foreach ([
        fn () => $api->connectionTest(),
        fn () => $api->createServer([]),
        fn () => $api->delete(1),
        fn () => $api->power('server-id', 'start'),
    ] as $action) {
        expect($action)->toThrow(PterodactylProviderException::class);
    }

    Http::assertNothingSent();
});

test('demo refuses Cloudflare registration and availability HTTP actions', function (): void {
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('cloudflare-domain', 'account_id', 'fake-account');
    $settings->set('cloudflare-domain', 'api_token', 'not-a-real-token', secret: true);
    $api = new HttpCloudflareApi($settings);

    expect(fn () => $api->check(['demo.example.test']))->toThrow(RuntimeException::class, 'disabled in the demo environment')
        ->and(fn () => $api->register('demo.example.test'))->toThrow(RuntimeException::class, 'disabled in the demo environment');

    Http::assertNothingSent();
});

test('demo refuses Cloudflare DNS reads and writes even with configured credentials', function (): void {
    $settings = app(ExtensionSettingsRepository::class);
    $settings->set('cloudflare-domain', 'account_id', 'fake-account');
    $settings->set('cloudflare-domain', 'api_token', 'not-a-real-token', secret: true);
    $api = new HttpCloudflareDnsApi($settings);

    foreach ([
        fn () => $api->findOrCreateZone('demo.example.test'),
        fn () => $api->listRecords('zone-id'),
        fn () => $api->createRecord('zone-id', ['type' => 'A', 'name' => 'demo', 'content' => '192.0.2.1']),
        fn () => $api->updateRecord('zone-id', 'record-id', ['content' => '192.0.2.2']),
        fn () => $api->deleteRecord('zone-id', 'record-id'),
    ] as $action) {
        expect($action)->toThrow(RuntimeException::class, 'disabled in the demo environment');
    }

    Http::assertNothingSent();
});
