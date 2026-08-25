<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Credits\CustomerCreditLedger;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Payments\RecordManualPayment;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

test('checkout reserves account balance until the remaining gateway amount is paid', function () {
    config(['agovena.payments.allow_development_instant_pay' => false]);
    app(PaymentGatewayRegistry::class)->clear();
    app(PaymentGatewayRegistry::class)->register(app(ManualPaymentGateway::class));

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    app(CartService::class)->add($product->id);
    app(CustomerCreditLedger::class)->credit($customer, 700, 'welcome_credit');

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'apply_credit' => true,
        'payment_method' => 'manual',
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

test('full account balance payment settles without a gateway', function () {
    config(['agovena.payments.allow_development_instant_pay' => false]);
    app(PaymentGatewayRegistry::class)->clear();

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 500, 'currency' => 'EUR']);
    app(CartService::class)->add($product->id);
    app(CustomerCreditLedger::class)->credit($customer, 500, 'welcome_credit');

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'apply_credit' => true,
    ]);

    $ledger = app(CustomerCreditLedger::class);

    expect($order->status->value)->toBe('paid')
        ->and($order->payment?->amount)->toBe(0)
        ->and($order->payment?->method)->toBe('account_balance')
        ->and($ledger->balance($customer))->toBe(0)
        ->and($ledger->reserved($customer))->toBe(0);
});

test('partial account balance without a gateway is rejected', function () {
    config(['agovena.payments.allow_development_instant_pay' => false]);
    app(PaymentGatewayRegistry::class)->clear();

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1000, 'currency' => 'EUR']);
    app(CartService::class)->add($product->id);
    app(CustomerCreditLedger::class)->credit($customer, 400, 'welcome_credit');

    expect(fn () => app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'apply_credit' => true,
    ]))->toThrow(ValidationException::class);
});
