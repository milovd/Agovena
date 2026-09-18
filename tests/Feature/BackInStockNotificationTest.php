<?php

declare(strict_types=1);

use App\Agovena\Notifications\BackInStockNotifier;
use App\Agovena\Notifications\SendBackInStockNotification;
use App\Models\BackInStockSubscription;
use App\Models\Product;
use App\Notifications\CataloguedMailNotification;
use Illuminate\Support\Facades\Notification;

it('stores back-in-stock subscriptions with a hashed unsubscribe token and upserts duplicates', function (): void {
    $product = Product::factory()->active()->create();
    $notifier = app(BackInStockNotifier::class);

    $token = $notifier->subscribe($product, ' Customer@Example.com ');
    $subscription = BackInStockSubscription::query()->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->email)->toBe('customer@example.com')
        ->and($subscription->token_hash)->not->toBe($token)
        ->and($notifier->unsubscribe(str_repeat('x', 64)))->toBeFalse()
        ->and($notifier->unsubscribe($token))->toBeTrue()
        ->and(BackInStockSubscription::query()->count())->toBe(0);
});

it('queues each pending subscriber once and marks delivery after dispatch', function (): void {
    Notification::fake();
    $product = Product::factory()->active()->create([
        'slug' => 'available-again',
        'name' => 'Available Again',
    ]);
    $notifier = app(BackInStockNotifier::class);
    $notifier->subscribe($product, 'one@example.com');
    $notifier->subscribe($product, 'two@example.com');

    expect($notifier->dispatch($product))->toBe(2);
    $subscriptions = BackInStockSubscription::query()->get();

    foreach ($subscriptions as $subscription) {
        (new SendBackInStockNotification($subscription->id))->handle();
    }

    expect(BackInStockSubscription::query()->whereNotNull('notified_at')->count())->toBe(2);
    Notification::assertSentOnDemand(CataloguedMailNotification::class, 2);

    expect($notifier->dispatch($product))->toBe(0);
});

it('does not send a claimed subscription twice when jobs overlap', function (): void {
    Notification::fake();
    $product = Product::factory()->active()->create(['slug' => 'one-only']);
    $subscriptionToken = app(BackInStockNotifier::class)->subscribe($product, 'one@example.com');
    $subscription = BackInStockSubscription::query()->firstOrFail();

    (new SendBackInStockNotification($subscription->id))->handle();
    (new SendBackInStockNotification($subscription->id))->handle();

    expect($subscriptionToken)->toHaveLength(64);
    Notification::assertSentOnDemand(CataloguedMailNotification::class, 1);
});
