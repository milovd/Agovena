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

it('lets referral managers create a referral code for a customer', function (): void {
    $staff = $this->createStaff([], ['referrals.view', 'referrals.manage']);
    $customer = Customer::factory()->create(['name' => 'Milo Vandaele']);

    Livewire::actingAs($staff)
        ->test(AdminReferrals::class)
        ->call('createCode')
        ->set('customerSearch', 'Milo')
        ->call('selectCustomer', $customer->id)
        ->assertSet('customerId', $customer->id)
        ->set('newCode', 'admin-ref')
        ->set('maxUses', 25)
        ->set('expiresAt', '2030-01-15T12:00')
        ->call('saveCode')
        ->assertHasNoErrors()
        ->assertSet('showCodeForm', false);

    $code = ReferralCode::query()->where('code', 'ADMIN-REF')->firstOrFail();

    expect($code->customer_id)->toBe($customer->id)
        ->and($code->max_uses)->toBe(25)
        ->and($code->expires_at?->format('Y-m-d H:i'))->toBe('2030-01-15 12:00')
        ->and($code->is_active)->toBeTrue();
});

it('keeps referral code creation behind the manage permission', function (): void {
    $staff = $this->createStaff([], ['referrals.view']);

    Livewire::actingAs($staff)
        ->test(AdminReferrals::class)
        ->assertDontSee(__('admin.referrals.add_code'))
        ->call('createCode')
        ->assertForbidden();
});

it('keeps customer search and selection inside one customer field', function (): void {
    $staff = $this->createStaff([], ['referrals.view', 'referrals.manage']);
    Customer::factory()->create(['name' => 'Milo Vandaele']);

    Livewire::actingAs($staff)
        ->test(AdminReferrals::class)
        ->call('createCode')
        ->set('customerSearch', 'Milo')
        ->assertSee('role="combobox"', false)
        ->assertSee('referral-customer-options', false)
        ->assertDontSee('referral-customer-search', false);
});
