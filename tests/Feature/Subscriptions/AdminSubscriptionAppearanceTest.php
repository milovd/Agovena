<?php

declare(strict_types=1);

use App\Agovena\Recurring\Enums\SubscriptionStatus;
use App\Agovena\Recurring\Http\Livewire\Admin\SubscriptionShow;
use App\Agovena\Recurring\Http\Livewire\Admin\SubscriptionsIndex;
use App\Agovena\Recurring\Models\Subscription;
use App\Livewire\Admin\PlanChanges\Index as PlanChangesIndex;
use App\Models\Product;
use App\Models\ProductPlanChange;
use Livewire\Livewire;
use Tests\Support\CreatesStaff;

uses(CreatesStaff::class);

function appearanceSubscription(string $number, SubscriptionStatus $status = SubscriptionStatus::Active): Subscription
{
    $product = Product::factory()->active()->create(['name' => 'Recurring service']);

    return Subscription::query()->create([
        'number' => $number,
        'customer_email' => 'subscriber@example.test',
        'product_id' => $product->id,
        'status' => $status,
        'interval' => 'month',
        'interval_count' => 1,
        'price_amount' => 1299,
        'currency' => 'EUR',
        'quantity' => 1,
        'current_period_start' => '2026-10-01',
        'current_period_end' => '2026-11-01',
        'next_billing_at' => '2026-11-01',
    ]);
}

test('subscription list preserves its filter, newest rows and readable mobile values', function (): void {
    $staff = $this->createStaff([], ['subscriptions.view']);
    $old = appearanceSubscription('SUB-OLDER', SubscriptionStatus::PastDue);
    $new = appearanceSubscription('SUB-NEWEST');

    $page = Livewire::actingAs($staff)->test(SubscriptionsIndex::class)
        ->assertSee('SUB-NEWEST')
        ->assertSee('SUB-OLDER')
        ->assertSee('Recurring service')
        ->assertSee('subscriber@example.test')
        ->assertSee(route('admin.subscriptions.show', $new), false);
    $html = $page->html();
    expect($html)->toContain('c-subscriptions__table')
        ->toContain('wire:model.live="status"')
        ->toContain('data-label="'.__('subscriptions::admin.customer').'"')
        ->toContain('data-label="'.__('subscriptions::admin.next_billing').'"')
        ->toContain('ag-badge--success');
    expect(strpos($html, 'SUB-NEWEST'))->toBeLessThan(strpos($html, 'SUB-OLDER'));

    $page->set('status', 'past_due')->assertSee($old->number)->assertDontSee($new->number);
});

test('empty subscription list retains the status filter and guidance', function (): void {
    Livewire::actingAs($this->createStaff([], ['subscriptions.view']))->test(SubscriptionsIndex::class)
        ->assertSee('wire:model.live="status"', false)
        ->assertSee(__('subscriptions::admin.empty'))
        ->assertDontSee('c-subscriptions__table', false);
});

test('subscription detail keeps all billing facts and guarded actions without invoking renewal', function (): void {
    $subscription = appearanceSubscription('SUB-DETAIL');
    $page = Livewire::actingAs($this->createStaff())->test(SubscriptionShow::class, ['subscription' => $subscription]);
    $html = $page->html();

    expect($html)->toContain('c-subscriptions__facts')
        ->toContain('subscriber@example.test')
        ->toContain('2026-11-01')
        ->toContain('ag-badge--success')
        ->toContain('wire:click="cancelAtPeriodEnd"')
        ->toContain('wire:click="cancelNow"')
        ->toContain('wire:click="markPastDue"')
        ->toContain('wire:click="createRenewal"')
        ->toContain('wire:confirm="'.__('subscriptions::admin.cancel_now_confirm').'"')
        ->toContain(route('admin.subscriptions.index'))
        ->toContain(__('subscriptions::admin.renewals_empty'));
});

test('subscription detail view-only staff see facts but no management controls', function (): void {
    $subscription = appearanceSubscription('SUB-READONLY');
    $page = Livewire::actingAs($this->createStaff([], ['subscriptions.view']))
        ->test(SubscriptionShow::class, ['subscription' => $subscription]);
    $page->assertSee('SUB-READONLY')
        ->assertSee('subscriber@example.test')
        ->assertDontSee('wire:click="cancelAtPeriodEnd"', false)
        ->assertDontSee('wire:click="cancelNow"', false)
        ->assertDontSee('wire:click="markPastDue"', false)
        ->assertDontSee('wire:click="createRenewal"', false);
});

test('plan configuration retains all controls, values and a confirmed deletion', function (): void {
    $staff = $this->createStaff([], ['plan-changes.view', 'plan-changes.manage']);
    $from = Product::factory()->active()->create(['name' => 'Starter plan']);
    $to = Product::factory()->active()->create(['name' => 'Pro plan']);
    $change = ProductPlanChange::query()->create([
        'from_product_id' => $from->id, 'to_product_id' => $to->id,
        'change_type' => 'upgrade', 'timing' => 'next_period', 'is_active' => true, 'sort' => 0,
    ]);

    $page = Livewire::actingAs($staff)->test(PlanChangesIndex::class);
    $html = $page->html();
    expect($html)->toContain('c-subscriptions__plan-form')
        ->toContain('c-subscriptions__plan-table')
        ->toContain('wire:submit="save"')
        ->toContain('wire:model.number="from_product_id"')
        ->toContain('wire:model.number="to_product_id"')
        ->toContain('wire:model="change_type"')
        ->toContain('wire:model="timing"')
        ->toContain('wire:model="is_active"')
        ->toContain('Starter plan')
        ->toContain('Pro plan')
        ->toContain('wire:click="delete('.$change->id.')"')
        ->toContain('wire:confirm="'.__('admin.plan_changes.delete_confirm').'"')
        ->toContain('data-label="'.__('admin.plan_changes.timing').'"')
        ->toContain('ag-badge--success');
    $page->set('from_product_id', '')->call('save')->assertHasErrors(['from_product_id' => 'required']);
});

test('plan select placeholder and confirmation are translated in both locales', function (string $locale): void {
    app()->setLocale($locale);
    $staff = $this->createStaff([], ['plan-changes.view', 'plan-changes.manage']);
    $from = Product::factory()->active()->create();
    $to = Product::factory()->active()->create();
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id, 'to_product_id' => $to->id,
        'change_type' => 'switch', 'timing' => 'immediate', 'is_active' => true, 'sort' => 0,
    ]);
    $html = Livewire::actingAs($staff)->test(PlanChangesIndex::class)->html();

    expect($html)->toContain('<option value="">'.__('common.select_placeholder').'</option>')
        ->toContain(__('admin.plan_changes.delete_confirm'))
        ->not->toContain('common.select</option>')
        ->not->toContain('admin.plan_changes.delete_confirm');
})->with(['en', 'nl']);

test('plan view-only staff retain the list but not save or delete', function (): void {
    $from = Product::factory()->active()->create(['name' => 'View-only starter']);
    $to = Product::factory()->active()->create(['name' => 'View-only pro']);
    ProductPlanChange::query()->create([
        'from_product_id' => $from->id, 'to_product_id' => $to->id,
        'change_type' => 'switch', 'timing' => 'immediate', 'is_active' => false, 'sort' => 0,
    ]);
    Livewire::actingAs($this->createStaff([], ['plan-changes.view']))->test(PlanChangesIndex::class)
        ->assertSee('View-only starter')
        ->assertSee('View-only pro')
        ->assertSee(__('common.inactive'))
        ->assertDontSee('wire:submit="save"', false)
        ->assertDontSee('wire:click="delete(', false);
});

test('subscription styles register mobile cards in the Admin stylesheet', function (): void {
    $path = resource_path('css/admin/screens/_subscriptions.css');
    $styles = is_file($path) ? (string) file_get_contents($path) : '';
    expect(file_get_contents(resource_path('css/admin.css')))->toContain("@import './admin/screens/_subscriptions.css';")
        ->and($styles)->toContain('@media (max-width: 720px)')
        ->toContain('.c-subscriptions__table tbody tr')
        ->toContain('.c-subscriptions__plan-table tbody tr')
        ->toContain('overflow-wrap: anywhere;');
});
