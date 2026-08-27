<?php

declare(strict_types=1);

use App\Livewire\Admin\Referrals\Index as AdminReferrals;
use App\Livewire\Customer\Referrals\Index as CustomerReferrals;
use App\Models\Customer;
use App\Models\ReferralCode;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('lets a customer create and view a referral code', function (): void {
    $customer = Customer::factory()->create();

    Livewire::actingAs($customer->user)
        ->test(CustomerReferrals::class)
        ->set('newCode', 'MILO-REF')
        ->call('createCode')
        ->assertHasNoErrors();

    expect(ReferralCode::query()->where('customer_id', $customer->id)->value('code'))->toBe('MILO-REF');
});

it('keeps referral review actions behind staff permissions', function (): void {
    $staff = $this->createStaff([], ['referrals.view']);

    Livewire::actingAs($staff)
        ->test(AdminReferrals::class)
        ->assertStatus(200);
});

it('presents referral management with the shared admin card and stats primitives', function (): void {
    $staff = $this->createStaff([], ['referrals.view', 'referrals.manage']);
    $customer = Customer::factory()->create();
    ReferralCode::query()->create([
        'customer_id' => $customer->id,
        'code' => 'DESIGN-REF',
        'uses_count' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($staff)
        ->get(route('admin.referrals.index'))
        ->assertOk()
        ->assertSee('referrals-overview', false)
        ->assertSee('ag-stats', false)
        ->assertSee('ag-card', false)
        ->assertSee('ag-table-wrap', false)
        ->assertSee('ag-badge', false)
        ->assertSee('ag-btn ag-btn--secondary ag-btn--sm', false);
});

it('lets referral managers disable and re-enable a referral code', function (): void {
    $staff = $this->createStaff([], ['referrals.view', 'referrals.manage']);
    $customer = Customer::factory()->create();
    $code = ReferralCode::query()->create([
        'customer_id' => $customer->id,
        'code' => 'ADMIN-REF',
        'uses_count' => 0,
        'is_active' => true,
    ]);

    $component = Livewire::actingAs($staff)->test(AdminReferrals::class)
        ->call('deactivateCode', $code->id)
        ->assertHasNoErrors();
    expect($code->fresh()->is_active)->toBeFalse();

    $component->call('activateCode', $code->id)->assertHasNoErrors();
    expect($code->fresh()->is_active)->toBeTrue();
});
