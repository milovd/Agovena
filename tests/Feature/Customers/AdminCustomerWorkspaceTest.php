<?php

declare(strict_types=1);

use App\Agovena\Auth\TotpTwoFactor;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Livewire\Admin\Customers\Show as AdminCustomerShow;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('admin customer workspace renders one continuous profile and activity view', function (): void {
    $customer = Customer::factory()->create([
        'name' => 'Workspace Customer',
        'email' => 'workspace@agovena.test',
    ]);

    Livewire::actingAs($this->createStaff())
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->assertSee('id="profile-heading"', false)
        ->assertSee('id="security-heading"', false)
        ->assertSee('id="activity-heading"', false)
        ->assertSee('id="actions-heading"', false)
        ->assertDontSee(__('admin.customer_properties.values_heading'), false)
        ->assertDontSee('role="tablist"', false)
        ->assertDontSee('wire:click="selectPanel', false);
});

test('admin must disable customer two factor before setting a password', function (): void {
    $customer = Customer::factory()->create();
    $newPassword = 'new-customer-password';
    $customer->user?->forceFill([
        'two_factor_secret' => app(TotpTwoFactor::class)->generateSecret(),
        'two_factor_confirmed_at' => now(),
    ])->save();
    $staff = $this->createStaff();

    $component = Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->set('password', $newPassword)
        ->set('password_confirmation', $newPassword)
        ->call('changePassword')
        ->assertHasErrors('password');

    expect($customer->user?->fresh()->hasTwoFactorEnabled())->toBeTrue();

    $component
        ->call('disableTwoFactor')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors()
        ->call('changePassword')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors();

    expect($customer->user?->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(Hash::check($newPassword, $customer->user?->fresh()?->password))->toBeTrue();
});

test('admin can add a customer credit after recent password confirmation', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->set('entry_type', 'credit')
        ->set('amount', 1250)
        ->set('reason', 'Service recovery')
        ->call('adjustCredit')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasNoErrors();

    expect(CustomerCreditEntry::query()
        ->where('customer_id', $customer->id)
        ->where('amount', 1250)
        ->where('reason', 'Service recovery')
        ->exists())->toBeTrue();
});

test('admin can completely delete an account without statutory records', function (): void {
    $customer = Customer::factory()->create();
    $customerId = $customer->id;
    $userId = $customer->user_id;
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('fullDelete')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertRedirect(route('admin.customers.index'));

    expect(Customer::query()->whereKey($customerId)->exists())->toBeFalse()
        ->and(User::query()->whereKey($userId)->exists())->toBeFalse();
});

test('complete deletion removes support data with the customer account', function (): void {
    $customer = Customer::factory()->create();
    $ticket = Ticket::query()->create([
        'number' => 'TCK-ERASURE-1',
        'customer_id' => $customer->id,
        'subject' => 'Personal support request',
        'status' => TicketStatus::Open,
        'priority' => TicketPriority::Normal,
    ]);
    $message = TicketMessage::query()->create([
        'ticket_id' => $ticket->id,
        'author_type' => 'customer',
        'author_id' => $customer->user_id,
        'body' => 'Private support message',
    ]);
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('fullDelete')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertRedirect(route('admin.customers.index'));

    expect(Ticket::query()->whereKey($ticket->id)->exists())->toBeFalse()
        ->and(TicketMessage::query()->whereKey($message->id)->exists())->toBeFalse();
});

test('admin full deletion preserves accounts with statutory records', function (): void {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create(['customer_id' => $customer->id]);
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('fullDelete')
        ->set('recentPassword', 'password')
        ->call('confirmRecentPassword')
        ->assertHasErrors('fullDelete');

    expect(Customer::query()->whereKey($customer->id)->exists())->toBeTrue()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

test('customer view-only staff cannot invoke workspace mutations', function (): void {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff([], ['customers.view']);
    expect($staff->can('customers.manage'))->toBeFalse();
    Livewire::actingAs($staff)
        ->test(AdminCustomerShow::class, ['customer' => $customer])
        ->call('fullDelete')
        ->assertForbidden();
});
