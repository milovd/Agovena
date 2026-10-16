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
