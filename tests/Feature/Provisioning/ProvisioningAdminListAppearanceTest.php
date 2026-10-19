<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\InstancesIndex;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('offers and applies the localized manual review status filter', function (): void {
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    $viewer = $this->createStaff(permissions: ['provisioning.view']);

    ServiceInstance::query()->create([
        'number' => 'SVC-FILTER-REVIEW',
        'status' => ServiceInstanceStatus::ManualReview,
        'provider_key' => 'manual',
        'customer_email' => 'review@example.test',
    ]);
    ServiceInstance::query()->create([
        'number' => 'SVC-FILTER-ACTIVE',
        'status' => ServiceInstanceStatus::Active,
        'provider_key' => 'manual',
        'customer_email' => 'active@example.test',
    ]);

    $originalLocale = app()->getLocale();
    try {
        foreach (['en', 'nl'] as $locale) {
            app()->setLocale($locale);
            $label = __('provisioning::status.manual_review');
            expect($label)->not->toBe('provisioning::status.manual_review');
            Livewire::actingAs($viewer)
                ->test(InstancesIndex::class)
                ->assertSee('<option value="manual_review">'.$label.'</option>', false)
                ->assertSee('SVC-FILTER-REVIEW')
                ->assertSee('SVC-FILTER-ACTIVE')
                ->set('status', ServiceInstanceStatus::ManualReview->value)
                ->assertSee('SVC-FILTER-REVIEW')
                ->assertDontSee('SVC-FILTER-ACTIVE');
        }
    } finally {
        app()->setLocale($originalLocale);
    }
});

it('registers responsive table cards for optional Admin lists', function (): void {
    $css = file_get_contents(resource_path('css/admin/components/_table.css'));
    expect($css)->toContain('@media (max-width: 720px)')
        ->toContain('.ag-table--mobile-cards tbody td::before');
});

it('keeps status and the detail action visible in labelled service rows for view-only staff', function (): void {
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    $viewer = $this->createStaff(permissions: ['provisioning.view']);
    $instance = ServiceInstance::query()->create([
        'number' => 'SVC-MOBILE-001',
        'status' => ServiceInstanceStatus::Active,
        'provider_key' => 'manual',
        'external_ref' => 'provider-mobile-1',
        'customer_email' => 'service@example.test',
    ]);

    Livewire::actingAs($viewer)
        ->test(InstancesIndex::class)
        ->assertSee('ag-table--mobile-cards', false)
        ->assertSee('data-label="'.__('provisioning::admin.status').'"', false)
        ->assertSee('ag-badge--success', false)
        ->assertSee('data-label="'.__('provisioning::admin.external_ref').'"', false)
        ->assertSee('provider-mobile-1')
        ->assertSee('href="'.route('admin.provisioning.show', $instance).'"', false)
        ->assertSee('class="ag-table__action-label"', false)
        ->assertSee('aria-label="'.__('provisioning::admin.details').' '.$instance->number.'"', false)
        ->assertDontSee(__('provisioning::admin.servers_title'));
});

it('uses the shared list page toolbar, accessible table headers and empty state', function (): void {
    installAndEnableModule('provisioning');
    app(SyncRegisteredPermissions::class)(force: true);
    $viewer = $this->createStaff(permissions: ['provisioning.view']);

    Livewire::actingAs($viewer)
        ->test(InstancesIndex::class)
        ->assertSee('class="admin-page ag-list-page"', false)
        ->assertSee('class="ag-toolbar ag-toolbar--filters"', false)
        ->assertSee('<label class="visually-hidden" for="provisioning-status">'.__('provisioning::admin.filter_status').'</label>', false)
        ->assertSee('id="provisioning-status" class="ag-select" wire:model.live="status"', false)
        ->assertDontSee('style="', false)
        ->assertSee('class="ag-empty" role="status"', false)
        ->assertSee(__('provisioning::admin.empty'));

    ServiceInstance::query()->create([
        'number' => 'SVC-HEADERS-001',
        'status' => ServiceInstanceStatus::Pending,
        'provider_key' => 'manual',
        'customer_email' => 'headers@example.test',
    ]);

    $html = Livewire::actingAs($viewer)->test(InstancesIndex::class)->html();
    expect(substr_count($html, '<th scope="col"'))->toBe(6)
        ->and($html)->toContain('<span class="visually-hidden">'.__('common.actions').'</span>')
        ->toContain('wire:loading.class="is-loading" wire:target="status"')
        ->not->toContain('<th></th>');
});
