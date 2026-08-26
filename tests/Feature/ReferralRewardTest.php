<?php

declare(strict_types=1);

use App\Agovena\Referrals\ReferralService;
use App\Agovena\Settings\SettingsRepository;
use App\Events\OrderPaid;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('posts a configured referral reward to the existing customer credit ledger once', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    $settings = app(SettingsRepository::class);
    $settings->set('referrals', 'enabled', true);
    $settings->set('referrals', 'reward_amount', 500);
    $settings->set('referrals', 'reward_currency', 'EUR');

    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'REWARD-REF');
    $order = Order::factory()->create(['customer_id' => $referred->id]);
    $attribution = $service->attribute($order, $code->code);

    $entry = $service->settle($order->fresh());
    $service->settle($order->fresh());

    expect($entry)->toBeInstanceOf(CustomerCreditEntry::class)
        ->and(CustomerCreditEntry::query()->where('reason', 'referral_reward')->count())->toBe(1)
        ->and(CustomerCreditEntry::query()->where('reason', 'referral_reward')->value('amount'))->toBe(500)
        ->and($attribution->fresh()->status)->toBe('posted')
        ->and(CustomerCreditEntry::query()->where('reason', 'referral_reward')->value('reference_id'))->toBe($attribution->id);
});

it('enforces referral expiry and maximum uses before attribution', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    app(SettingsRepository::class)->set('referrals', 'enabled', true);
    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'LIMIT-REF', maxUses: 1, expiresAt: Carbon::yesterday());
    $order = Order::factory()->create(['customer_id' => $referred->id]);

    expect(fn () => $service->attribute($order, $code->code))->toThrow(ValidationException::class);

    $code->update(['expires_at' => Carbon::tomorrow()]);
    $service->attribute($order, $code->code);
    $secondOrder = Order::factory()->create(['customer_id' => $referred->id]);

    expect(fn () => $service->attribute($secondOrder, $code->code))->toThrow(ValidationException::class);
});

it('settles a referral reward when the existing order-paid event is dispatched', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    $settings = app(SettingsRepository::class);
    $settings->set('referrals', 'enabled', true);
    $settings->set('referrals', 'reward_amount', 300);
    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'EVENT-REF');
    $order = Order::factory()->create(['customer_id' => $referred->id]);
    $service->attribute($order, $code->code);

    OrderPaid::dispatch($order);

    expect(CustomerCreditEntry::query()->where('reason', 'referral_reward')->value('amount'))->toBe(300);
});

it('holds a reward for fraud review without changing customer credit', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    $settings = app(SettingsRepository::class);
    $settings->set('referrals', 'enabled', true);
    $settings->set('referrals', 'reward_amount', 500);
    $settings->set('referrals', 'fraud_review_required', true);
    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'REVIEW-REF');
    $order = Order::factory()->create(['customer_id' => $referred->id]);
    $attribution = $service->attribute($order, $code->code);

    expect($service->settle($order->fresh()))->toBeNull()
        ->and($attribution->fresh()->status)->toBe('review')
        ->and(CustomerCreditEntry::query()->where('reason', 'referral_reward')->exists())->toBeFalse();
});
