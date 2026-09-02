<?php

declare(strict_types=1);

use App\Agovena\Referrals\ReferralService;
use App\Agovena\Settings\SettingsRepository;
use App\Enums\PaymentStatus;
use App\Livewire\Admin\Referrals\Index as AdminReferrals;
use App\Livewire\Admin\Settings\Hub;
use App\Livewire\Customer\Referrals\Index as CustomerReferrals;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ReferralAttribution;
use App\Models\ReferralVisit;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

it('records a referral link visit and sets a tracking cookie', function (): void {
    $referrer = Customer::factory()->create();
    app(SettingsRepository::class)->set('store', 'referrals_enabled', true);
    app(SettingsRepository::class)->set('store', 'referral_window_days', 30);
    $code = app(ReferralService::class)->createCode($referrer, 'SHARE-REF');

    $response = $this->get(route('referrals.visit', ['code' => $code->code]));

    $response->assertRedirect(route('storefront.home'))
        ->assertCookie(ReferralService::TRACKING_COOKIE)
        ->assertCookie(ReferralService::VISITOR_COOKIE);

    expect(ReferralVisit::query()->where('referral_code_id', $code->id)->count())->toBe(1)
        ->and(ReferralVisit::query()->where('referral_code_id', $code->id)->value('clicks_count'))->toBe(1);
});

it('honors the tracking window and rewards only the first paid purchase', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    app(SettingsRepository::class)->set('store', 'referrals_enabled', true);
    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'FIRST-REF', windowDays: 14);
    $visit = $service->recordVisit($code->code, hash('sha256', 'browser-1'));

    $firstOrder = Order::factory()->create([
        'customer_id' => $referred->id,
        'subtotal_amount' => 10_000,
        'discount_amount' => 0,
        'total_amount' => 10_000,
    ]);
    $attribution = $service->attribute($firstOrder, $code->code, $visit->id);
    $service->settle($firstOrder->fresh());

    $secondOrder = Order::factory()->create(['customer_id' => $referred->id]);

    expect($attribution)->toBeInstanceOf(ReferralAttribution::class)
        ->and($service->attribute($secondOrder, $code->code, $visit->id))->toBeNull()
        ->and($attribution->fresh()->purchased_at)->not->toBeNull()
        ->and($visit->fresh()->converted_at)->not->toBeNull()
        ->and(CustomerCreditEntry::query()->where('reason', 'referral_reward')->count())->toBe(1);

    $visit->update(['expires_at' => Carbon::now()->subMinute()]);
    expect($service->visitFromCookie($service->cookieValue($visit->fresh())))->toBeNull();
});

it('supports store and per-code referral window defaults', function (): void {
    $referrer = Customer::factory()->create();
    $settings = app(SettingsRepository::class);
    $settings->set('store', 'referral_window_days', 45);
    $service = app(ReferralService::class);

    $storeDefaultCode = $service->createCode($referrer, 'DEFAULT-DAYS');
    $overrideCode = $service->createCode($referrer, 'OVERRIDE-DAYS', windowDays: 7);

    expect($service->defaultWindowDays())->toBe(45)
        ->and($service->windowDaysFor($storeDefaultCode))->toBe(45)
        ->and($service->windowDaysFor($overrideCode))->toBe(7);
});

it('lets referral managers override the attribution window for one code', function (): void {
    $staff = $this->createStaff();
    $customer = Customer::factory()->create();
    $code = app(ReferralService::class)->createCode($customer, 'ADMIN-DAYS');

    Livewire::actingAs($staff)
        ->test(AdminReferrals::class)
        ->call('editCode', $code->id)
        ->set('windowDays', 14)
        ->call('saveCode')
        ->assertHasNoErrors();

    expect($code->fresh()->window_days)->toBe(14);
});

it('does not attribute an existing customer with a previous paid order', function (): void {
    $referrer = Customer::factory()->create();
    $referred = Customer::factory()->create();
    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'EXISTING-CUSTOMER');
    $previousOrder = Order::factory()->create(['customer_id' => $referred->id]);
    Payment::factory()->create([
        'order_id' => $previousOrder->id,
        'status' => PaymentStatus::Paid,
    ]);
    $newOrder = Order::factory()->create(['customer_id' => $referred->id]);

    expect($service->attribute($newOrder, $code->code))->toBeNull()
        ->and(ReferralAttribution::query()->count())->toBe(0);
});

it('shows affiliate link and conversion statistics in the customer tab', function (): void {
    $referrer = Customer::factory()->create();
    app(SettingsRepository::class)->set('store', 'referrals_enabled', true);
    $service = app(ReferralService::class);
    $code = $service->createCode($referrer, 'DASHBOARD-REF');
    $visit = $service->recordVisit($code->code, hash('sha256', 'browser-2'));
    $visit->update(['clicks_count' => 3]);

    $order = Order::factory()->create(['customer_id' => Customer::factory()->create()->id]);
    $attribution = $service->attribute($order, $code->code, $visit->id);
    $attribution->update(['status' => 'posted', 'purchased_at' => now(), 'credited_at' => now()]);

    Livewire::actingAs($referrer->user)
        ->test(CustomerReferrals::class)
        ->assertSee(route('referrals.visit', ['code' => $code->code]), false)
        ->assertSee(__('customer.referrals.affiliate_title'), false)
        ->assertSee(__('customer.referrals.affiliate_lede', ['percentage' => 10]))
        ->assertSee(__('customer.referrals.per_first_purchase'), false)
        ->assertSee(__('customer.referrals.window_label'), false)
        ->assertSee(__('customer.referrals.link_clicks'), false)
        ->assertSee(__('customer.referrals.paid_purchases'), false)
        ->assertSee(__('customer.referrals.earned_rewards'), false)
        ->assertSee(__('customer.referrals.share_heading'), false)
        ->assertDontSee(__('customer.referrals.link_visits'), false)
        ->assertDontSee(__('customer.referrals.activity_heading'), false)
        ->assertDontSee(__('customer.referrals.codes_heading'), false)
        ->assertSee('3', false);
});

it('lets store managers configure the default referral window', function (): void {
    $staff = $this->createStaff();

    Livewire::actingAs($staff)
        ->test(Hub::class)
        ->set('tab', 'store')
        ->set('values.referral_window_days', 21)
        ->call('save')
        ->assertHasNoErrors();

    expect((int) app(SettingsRepository::class)->get('store', 'referral_window_days'))->toBe(21);
});
