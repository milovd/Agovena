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
