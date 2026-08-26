<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Invoices\IssueInvoiceFromOrder;
use App\Agovena\Payments\Gateways\ManualPaymentGateway;
use App\Agovena\Payments\PaymentGatewayRegistry;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\InvoiceItemKind;
use App\Models\Customer;
use App\Models\Product;

it('snapshots a configured gateway fee on the order and payment amount', function (): void {
    config(['agovena.payments.allow_development_instant_pay' => false]);
    app(PaymentGatewayRegistry::class)->clear();
    app(PaymentGatewayRegistry::class)->register(app(ManualPaymentGateway::class));
    app(SettingsRepository::class)->set('payments', 'gateway_fee_rules', [
        'manual' => [
            'enabled' => true,
            'percentage_bps' => 1_000,
            'fixed_amount' => 20,
            'currency' => 'EUR',
        ],
    ]);

    $customer = Customer::factory()->create();
    $product = Product::factory()->active()->create(['price_amount' => 1_000, 'currency' => 'EUR']);
    app(CartService::class)->add($product->id);

    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_id' => $customer->id,
        'payment_method' => 'manual',
    ]);

    expect($order->total_amount)->toBe(1_120)
        ->and($order->payment_fee_amount)->toBe(120)
        ->and($order->payment_fee_snapshot)->toMatchArray([
            'gateway_id' => 'manual',
            'percentage_bps' => 1_000,
            'fixed_amount' => 20,
        ])
        ->and($order->payment?->amount)->toBe(1_120);

    $invoice = app(IssueInvoiceFromOrder::class)->handle($order);

    expect($invoice->payment_fee_amount)->toBe(120)
        ->and($invoice->payment_fee_snapshot)->toMatchArray(['gateway_id' => 'manual'])
        ->and($invoice->items->filter(fn ($item): bool => $item->kind === InvoiceItemKind::PaymentFee))->toHaveCount(1);
});
