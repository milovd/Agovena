<?php

declare(strict_types=1);

use App\Agovena\Auth\TotpTwoFactor;
use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Payments\RecordManualPayment;
use App\Agovena\Privacy\AnonymizeCustomer;
use App\Agovena\Support\CreateTicket;
use App\Agovena\Support\ReplyToTicket;
use App\Livewire\Customer\Account\TicketShow;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('customer creates a ticket and sees a staff reply', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff();
    $ticket = app(CreateTicket::class)->handle($customer, 'Need help', 'My order needs attention.');

    app(ReplyToTicket::class)->byStaff($ticket, $staff, 'We are checking this for you.');

    Livewire::actingAs($customer->user)
        ->test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('We are checking this for you.');
});

test('customer cannot see internal staff notes', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff();
    $ticket = app(CreateTicket::class)->handle($customer, 'Private test', 'Visible customer message.');

    app(ReplyToTicket::class)->byStaff($ticket, $staff, 'Internal investigation details.', true);

    Livewire::actingAs($customer->user)
        ->test(TicketShow::class, ['ticket' => $ticket])
        ->assertSee('Visible customer message.')
        ->assertDontSee('Internal investigation details.');
});

test('credit ledger refuses an overdraft', function () {
    $customer = Customer::factory()->create();
    $ledger = app(CustomerCreditLedger::class);
    $ledger->credit($customer, 500, 'welcome_credit');

    expect(fn () => $ledger->debit($customer, 501, 'too_much'))
        ->toThrow(ValidationException::class)
        ->and($ledger->balance($customer))->toBe(500);
});

test('gdpr anonymization keeps order rows', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
    ]);

    app(AnonymizeCustomer::class)->handle($customer);

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and($order->fresh()?->customer_id)->toBe($customer->id)
        ->and($customer->fresh()?->anonymized_at)->not->toBeNull()
        ->and($customer->fresh()?->email)->toBe('deleted+'.$customer->id.'@anonymized.invalid');
});

test('gdpr anonymization clears customer two factor material', function () {
    $customer = Customer::factory()->create();
    $customer->user?->forceFill([
        'two_factor_secret' => app(TotpTwoFactor::class)->generateSecret(),
        'two_factor_recovery_codes' => [Str::random(32)],
        'two_factor_confirmed_at' => now(),
    ])->save();

    app(AnonymizeCustomer::class)->handle($customer);

    $user = $customer->user?->fresh();
    expect($user?->two_factor_secret)->toBeNull()
        ->and($user?->two_factor_recovery_codes)->toBeNull()
        ->and($user?->two_factor_confirmed_at)->toBeNull();
});

test('staff credit adjustment creates an audit entry', function () {
    $customer = Customer::factory()->create();
    $staff = $this->createStaff();
    $this->actingAs($staff);

    app(CustomerCreditLedger::class)->credit($customer, 1000, 'service_recovery', staff: $staff);

    $log = AuditLog::query()->where('action', 'customer_credit.adjusted')->first();
    expect($log)->not->toBeNull()
        ->and($log?->actor_type)->toBe('staff')
        ->and($log?->actor_id)->toBe($staff->id)
        ->and($log?->subject_id)->toBe($customer->id);
});

test('checkout applies credit after pricing and reduces payment amount', function () {
    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    app(CartService::class)->add($product->id);
    app(CustomerCreditLedger::class)->credit($customer, 700, 'welcome_credit');

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'apply_credit' => true,
    ]);

    $ledger = app(CustomerCreditLedger::class);

    expect($order->total_amount)->toBe(1000)
        ->and($order->credit_amount)->toBe(700)
        ->and($order->payment?->amount)->toBe(300)
        ->and($ledger->available($customer))->toBe(0)
        ->and($ledger->reserved($customer))->toBe(700)
        ->and($ledger->balance($customer))->toBe(700);

    app(RecordManualPayment::class)->handle($order, test()->createStaff());

    expect($ledger->available($customer))->toBe(0)
        ->and($ledger->reserved($customer))->toBe(0)
        ->and($ledger->balance($customer))->toBe(0);
});
