<?php

declare(strict_types=1);

use App\Agovena\Settings\SettingsRepository;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('keeps system table facts and actions labelled on narrow screens', function (): void {
    $webhooks = file_get_contents(resource_path('views/livewire/admin/webhooks/index.blade.php'));
    $jobs = file_get_contents(resource_path('views/livewire/admin/system/failed-jobs.blade.php'));
    $entry = file_get_contents(resource_path('css/admin.css'));

    expect($webhooks)->toContain('class="ag-table c-system-table"')
        ->toContain('data-label="{{ __(\'admin.webhooks.url\') }}"')
        ->toContain('data-label="{{ __(\'admin.webhooks.attempts\') }}"')
        ->toContain('data-label="{{ __(\'common.actions\') }}"')
        ->and($jobs)->toContain('class="ag-table c-system-table"')
        ->toContain('data-label="{{ __(\'admin.failed_jobs.exception\') }}"')
        ->toContain('data-label="{{ __(\'common.actions\') }}"')
        ->and($entry)->toContain("@import './admin/screens/_system.css';");
});

it('renders populated system cards with Dutch labels for view-only staff', function (): void {
    app(SettingsRepository::class)->set('general', 'locale', 'nl');
    $viewer = $this->createStaff([], ['webhooks.view', 'jobs.view']);
    WebhookEndpoint::query()->create([
        'name' => 'QA endpoint', 'url' => 'https://example.test/qa',
        'secret' => 'test-only-placeholder', 'events' => ['order.created'], 'active' => true,
    ]);
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(), 'connection' => 'database',
        'queue' => 'qa', 'payload' => '{}', 'exception' => 'QA failure', 'failed_at' => now(),
    ]);

    $this->actingAs($viewer)->get('/admin/webhooks')
        ->assertOk()
        ->assertSee('QA endpoint')
        ->assertSee('data-label="'.__('admin.webhooks.url', [], 'nl').'"', false)
        ->assertSee('data-label="'.__('admin.webhooks.status', [], 'nl').'"', false)
        ->assertDontSee('wire:submit="save"', false);

    $this->actingAs($viewer)->get('/admin/failed-jobs')
        ->assertOk()
        ->assertSee('QA failure')
        ->assertSee('data-label="'.__('admin.failed_jobs.exception', [], 'nl').'"', false)
        ->assertDontSee('wire:click="forget(', false);
});
