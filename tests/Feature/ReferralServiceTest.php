<?php

declare(strict_types=1);

use App\Agovena\Cart\CartService;
use App\Agovena\Checkout\PlaceOrder;
use App\Agovena\Customer\AddressData;
use App\Agovena\Referrals\ReferralService;
use App\Agovena\Settings\SettingsRepository;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReferralAttribution;
use App\Models\ReferralCode;
use Illuminate\Validation\ValidationException;

it('creates an active referral code and attributes a referred order once', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    app(SettingsRepository::class)->set('referrals', 'enabled', true);

    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'MILO-REF');
    $order = Order::factory()->create(['customer_id' => $referred->id]);

    $attribution = $service->attribute($order, $code->code);
    $service->attribute($order->fresh(), $code->code);

    expect($attribution->referrer_customer_id)->toBe($referrer->id)
        ->and(ReferralAttribution::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($order->fresh()->referral_code)->toBe('MILO-REF')
        ->and(ReferralCode::query()->where('code', 'MILO-REF')->value('uses_count'))->toBe(1);
});

it('rejects self referral and disabled referral policy', function (): void {
    $customer = Customer::factory()->create();
    app(SettingsRepository::class)->set('referrals', 'enabled', true);
    $service = app(ReferralService::class);
    $code = $service->createCode($customer, 'SELF-REF');
    $order = Order::factory()->create(['customer_id' => $customer->id]);

    expect(fn () => $service->attribute($order, $code->code))->toThrow(ValidationException::class);

    app(SettingsRepository::class)->set('referrals', 'enabled', false);
    $other = Customer::factory()->create();
    $otherOrder = Order::factory()->create(['customer_id' => $other->id]);

    expect($service->attribute($otherOrder, $code->code))->toBeNull();
});

it('attributes a referral code during checkout', function (): void {
    app(SettingsRepository::class)->set('referrals', 'enabled', true);
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    $code = app(ReferralService::class)->createCode($referrer, 'CHECKOUT-REF');
    $product = Product::factory()->active()->create(['price_amount' => 1000]);

    app(CartService::class)->add($product->id, 1);
    $order = app(PlaceOrder::class)->handle([
        'customer_name' => $referred->name,
        'customer_email' => $referred->email,
        'customer_id' => $referred->id,
        'referral_code' => $code->code,
        'billing' => AddressData::fromArray([
            'name' => $referred->name,
            'line1' => 'Referral Street 1',
            'city' => 'Amsterdam',
            'postal_code' => '1000 AA',
            'country' => 'NL',
        ]),
    ]);

    expect($order->fresh()->referral_code)->toBe('CHECKOUT-REF')
        ->and(ReferralAttribution::query()->where('order_id', $order->id)->exists())->toBeTrue();
});
