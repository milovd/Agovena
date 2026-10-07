<?php

declare(strict_types=1);

use Agovena\Extensions\Convoy\HttpConvoyApi;
use Agovena\Extensions\CPanel\HttpCPanelApi;
use Agovena\Modules\Provisioning\Support\ServerProviderException;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Extensions\ExtensionSettingsRepository;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    installAndEnableModule('provisioning');
    app(ExtensionManager::class)->discover();
});

it('never lets the shared server API client follow redirects', function (string $apiClass, array $connection, array $body) {
    $options = null;
    Http::preventStrayRequests();
    Http::fake(function ($request, array $requestOptions) use (&$options, $body) {
        $options = $requestOptions;

        return Http::response($body, 200);
    });

    (new $apiClass(app(ExtensionSettingsRepository::class), $connection))->connectionTest();

    expect($options['allow_redirects'] ?? null)->toBeFalse()
        ->and($options['verify'] ?? null)->toBeTrue();
    Http::assertSentCount(1);
})->with([
    'cpanel' => [
        HttpCPanelApi::class,
        ['api_url' => 'https://whm.example.test:2087', 'api_username' => 'root', 'api_token' => 'test-token'],
        ['metadata' => ['command' => 'version', 'result' => 1], 'data' => ['version' => '11.120.0.1']],
    ],
    'convoy' => [
        HttpConvoyApi::class,
        ['api_url' => 'https://convoy.example.test', 'api_token' => 'test-token'],
        ['data' => [], 'meta' => ['pagination' => ['total' => 0]]],
    ],
]);

it('refuses a WHM connection without an API username before any network call', function () {
    Http::preventStrayRequests();
    Http::fake();

    $api = new HttpCPanelApi(app(ExtensionSettingsRepository::class), [
        'api_url' => 'https://whm.example.test:2087',
        'api_token' => 'test-token',
    ]);

    expect(fn () => $api->connectionTest())->toThrow(ServerProviderException::class, 'errors.not_configured');
    Http::assertNothingSent();
});
