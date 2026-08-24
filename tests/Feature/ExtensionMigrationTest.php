<?php

declare(strict_types=1);

use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Installation\ApplicationSchemaStatus;
use App\Agovena\Modules\ModuleManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function resetMollieSchema(): void
{
    Schema::dropIfExists('mollie_mandates');
    DB::table('migrations')->where('migration', 'like', '%mollie%')->delete();
}

test('module install and enable run database migrations', function () {
    expect(Schema::hasTable('subscriptions'))->toBeFalse();

    $modules = app(ModuleManager::class);
    $modules->install('subscriptions');
    expect(Schema::hasTable('subscriptions'))->toBeTrue();

    $modules->disable('subscriptions');
    Schema::drop('subscription_renewals');
    Schema::drop('subscriptions');
    DB::table('migrations')->where('migration', 'like', '%subscription%')->delete();
    expect(Schema::hasTable('subscriptions'))->toBeFalse();

    $modules->enable('subscriptions');
    expect(Schema::hasTable('subscriptions'))->toBeTrue();
});

test('extension install and enable run database migrations', function () {
    resetMollieSchema();
    expect(Schema::hasTable('mollie_mandates'))->toBeFalse();

    $extensions = app(ExtensionManager::class);
    $extensions->install('mollie');
    expect(Schema::hasTable('mollie_mandates'))->toBeTrue();

    $extensions->disable('mollie');
    Schema::drop('mollie_mandates');
    DB::table('migrations')->where('migration', 'like', '%mollie_mandates%')->delete();
    expect(Schema::hasTable('mollie_mandates'))->toBeFalse();

    $extensions->enable('mollie');
    expect(Schema::hasTable('mollie_mandates'))->toBeTrue();
});

test('agovena upgrade migrates installed extensions', function () {
    resetMollieSchema();

    $extensions = app(ExtensionManager::class);
    $extensions->install('mollie');
    $extensions->disable('mollie');

    Schema::drop('mollie_mandates');
    DB::table('migrations')->where('migration', 'like', '%mollie_mandates%')->delete();

    expect(Schema::hasTable('mollie_mandates'))->toBeFalse();

    Artisan::call('agovena:upgrade');

    expect(Schema::hasTable('mollie_mandates'))->toBeTrue();
});

test('enabled extension migrations are included in schema status', function () {
    installAndEnableExtension('mollie');
    app(ApplicationSchemaStatus::class)->refresh();

    expect(app(ApplicationSchemaStatus::class)->isCurrent())->toBeTrue();
});
