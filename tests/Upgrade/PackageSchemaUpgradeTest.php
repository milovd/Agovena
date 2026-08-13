<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('package catalog schema can be applied onto an existing store', function () {
    Artisan::call('migrate');

    expect(Schema::hasTable('agovena_packages'))->toBeTrue()
        ->and(Schema::hasTable('agovena_modules'))->toBeTrue();

    DB::table('agovena_modules')->insert([
        'module_id' => 'inventory',
        'version' => '1.0.0',
        'enabled' => false,
        'installed_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Schema::dropIfExists('agovena_packages');
    DB::table('migrations')->where('migration', '2026_08_13_100000_create_agovena_packages_table')->delete();

    expect(Schema::hasTable('agovena_packages'))->toBeFalse()
        ->and(DB::table('agovena_modules')->where('module_id', 'inventory')->exists())->toBeTrue();

    Artisan::call('migrate');

    expect(Schema::hasTable('agovena_packages'))->toBeTrue()
        ->and(DB::table('agovena_modules')->where('module_id', 'inventory')->exists())->toBeTrue();
});
