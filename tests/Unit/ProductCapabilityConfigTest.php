<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductCapability;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

it('encrypts capability config regardless of capability type or assignment order', function (): void {
    $capability = new ProductCapability;
    $capability->config = [
        'api_key' => 'do-not-store',
        'region' => 'eu-west',
    ];
    $capability->capability = 'custom';

    $attributes = $capability->getAttributes();

    expect($attributes['config_encrypted'] ?? null)->toBeString()->not->toBe('')
        ->and($attributes['config'])->toContain('[REDACTED]')
        ->and($attributes['config'])->not->toContain('do-not-store')
        ->and($capability->config)->toMatchArray([
            'api_key' => '[REDACTED]',
            'region' => 'eu-west',
        ])
        ->and($capability->runtimeConfig())->toMatchArray([
            'api_key' => 'do-not-store',
            'region' => 'eu-west',
        ]);
});

it('fails closed when encrypted capability config is missing or cannot be decrypted', function (): void {
    $product = Product::factory()->create();
    $capability = new ProductCapability;
    $capability->product_id = $product->id;
    $capability->capability = 'provisionable';
    $capability->config = ['provider_settings' => ['environment' => 'SECRET=value']];
    $capability->save();

    DB::table('product_capabilities')
        ->where('id', $capability->id)
        ->update(['config_encrypted' => 'invalid-ciphertext']);

    expect($capability->fresh()->runtimeConfig())->toBeNull()
        ->and($capability->fresh()->hasCorruptConfig())->toBeTrue();
});

it('quarantines malformed legacy capability config during encryption migration', function (): void {
    $product = Product::factory()->create();
    $id = DB::table('product_capabilities')->insertGetId([
        'product_id' => $product->id,
        'capability' => 'provisionable',
        'config' => '{malformed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path('migrations/2026_08_28_220500_encrypt_product_capability_config.php');
    $migration->up();
    $row = DB::table('product_capabilities')->where('id', $id)->first();

    expect($row->config)->toContain('invalid_legacy_config')
        ->and($row->config)->not->toContain('{malformed')
        ->and(Crypt::decryptString((string) $row->config_encrypted))
        ->toBe(json_encode(['_migration_status' => 'invalid_legacy_config']));
});

it('rejects encrypted quarantine markers as corrupt runtime configuration', function (): void {
    $product = Product::factory()->create();
    $capability = new ProductCapability;
    $capability->product_id = $product->id;
    $capability->capability = 'provisionable';
    $capability->config = ['_migration_status' => 'invalid_legacy_config'];
    $capability->save();

    $fresh = $capability->fresh();

    expect($fresh->hasCorruptConfig())->toBeTrue()
        ->and($fresh->runtimeConfig())->toBeNull();
});
