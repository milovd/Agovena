<?php

declare(strict_types=1);

use App\Livewire\Admin\System\FailedJobs;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('shows failed job facts to viewers without retry or forget controls', function (): void {
    $uuid = (string) Str::uuid();
    $id = DB::table('failed_jobs')->insertGetId([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Fixture failure',
        'failed_at' => now(),
    ]);
    $viewer = $this->createStaff([], ['jobs.view']);

    Livewire::actingAs($viewer)
        ->test(FailedJobs::class)
        ->assertSee(__('admin.failed_jobs.details_hidden'))
        ->assertSee(__('admin.failed_jobs.lede_viewer'))
        ->assertDontSee('Retry or delete them here.')
        ->assertDontSee('Fixture failure')
        ->assertDontSee('wire:click="retry(', false)
        ->assertDontSee('wire:click="forget(', false);

    Livewire::actingAs($viewer)->test(FailedJobs::class)->call('retry', $uuid)->assertForbidden();
    Livewire::actingAs($viewer)->test(FailedJobs::class)->call('forget', $id)->assertForbidden();
    expect(DB::table('failed_jobs')->where('id', $id)->exists())->toBeTrue();
});

it('keeps failed job controls available for managers', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Fixture failure',
        'failed_at' => now(),
    ]);
    $manager = $this->createStaff([], ['jobs.view', 'jobs.manage']);

    Livewire::actingAs($manager)
        ->test(FailedJobs::class)
        ->assertSee('wire:click="retry(', false)
        ->assertSee('wire:click="forget(', false);
});

it('shows only an exception type rather than its potentially sensitive message', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => "RuntimeException: private failure context marker\nTrace content marker",
        'failed_at' => now(),
    ]);
    $manager = $this->createStaff([], ['jobs.view', 'jobs.manage']);

    Livewire::actingAs($manager)
        ->test(FailedJobs::class)
        ->assertSee('RuntimeException')
        ->assertDontSee('private failure context marker')
        ->assertDontSee('Trace content marker');
});
