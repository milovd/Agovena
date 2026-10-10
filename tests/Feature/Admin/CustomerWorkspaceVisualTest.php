<?php

declare(strict_types=1);

use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Models\Customer;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('customer workspace presents four compact summary metrics and identifiable sections', function (): void {
    $customer = Customer::factory()->create();

    $component = Livewire::actingAs($this->createStaff())
        ->test(AdminCustomerShow::class, ['customer' => $customer]);

    expect(substr_count($component->html(), 'customer-workspace__metric-icon'))->toBe(4);

    $component
        ->assertSee('customer-workspace__section-icon', false)
        ->assertSee('customer-workspace__activity-grid', false)
        ->assertSee('id="profile-heading"', false)
        ->assertSee('id="activity-heading"', false)
        ->assertSee('id="capabilities-heading"', false)
        ->assertSee('id="security-heading"', false)
        ->assertSee('id="credits-heading"', false)
        ->assertSee('id="access-heading"', false)
        ->assertSee('id="actions-heading"', false)
        ->assertSee('wire:submit="saveProfile"', false)
        ->assertSee('wire:submit="adjustCredit"', false)
        ->assertSee('wire:submit="saveRoles"', false)
        ->assertSee('wire:submit="changePassword"', false)
        ->assertSee('wire:click="anonymize"', false);
});

test('view only customer staff retains context without editing or lifecycle controls', function (): void {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff([], ['customers.view']);

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->assertSee('id="profile-heading"', false)
        ->assertSee('id="activity-heading"', false)
        ->assertSee('id="security-heading"', false)
        ->assertSee('id="credits-heading"', false)
        ->assertDontSee('wire:submit="saveProfile"', false)
        ->assertDontSee('wire:submit="adjustCredit"', false)
        ->assertDontSee('wire:submit="saveRoles"', false)
        ->assertDontSee('wire:submit="changePassword"', false)
        ->assertDontSee('wire:click="markEmailUnverified"', false)
        ->assertDontSee('wire:click="markEmailVerified"', false)
        ->assertDontSee('wire:click="anonymize"', false)
        ->assertDontSee('id="actions-heading"', false);
});
