<?php

declare(strict_types=1);

use App\Agovena\Notifications\VapidKeyStore;
use App\Models\Setting;

it('stores VAPID keys encrypted and returns stable key material', function (): void {
    $keys = [
        'publicKey' => rtrim(strtr(base64_encode(random_bytes(65)), '+/', '-_'), '='),
        'privateKey' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
    ];
    $store = app(VapidKeyStore::class);

    $store->put('https://agovena.example.test', $keys);

    expect($store->get())->toBe([
        'subject' => 'https://agovena.example.test',
        'publicKey' => $keys['publicKey'],
        'privateKey' => $keys['privateKey'],
    ]);

    $stored = Setting::query()
        ->where('group', 'notifications')
        ->where('key', 'vapid_private_key')
        ->value('value');

    expect($stored)->not->toBe($keys['privateKey'])
        ->and($stored)->not->toContain($keys['privateKey']);
});
