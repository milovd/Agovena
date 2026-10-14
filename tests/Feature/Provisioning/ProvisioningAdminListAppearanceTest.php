<?php

declare(strict_types=1);

use Agovena\Modules\Provisioning\Enums\ServiceInstanceStatus;
use Agovena\Modules\Provisioning\Http\Livewire\Admin\InstancesIndex;
use Agovena\Modules\Provisioning\Models\ServiceInstance;
use App\Agovena\Permissions\SyncRegisteredPermissions;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

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
