<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\BackInStockSubscription;
use App\Notifications\CataloguedMailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

final class SendBackInStockNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $subscriptionId)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $subscription = BackInStockSubscription::query()->with('product')->find($this->subscriptionId);
        if (! $subscription instanceof BackInStockSubscription || $subscription->notified_at !== null) {
            return;
        }

        $claimed = BackInStockSubscription::query()
            ->whereKey($subscription->id)
            ->whereNull('notified_at')
            ->where(function ($query): void {
                $query->whereNull('delivery_claimed_at')
                    ->orWhere('delivery_claimed_at', '<', now()->subMinutes(15));
            })
            ->update([
                'delivery_claimed_at' => now(),
                'delivery_attempts' => $subscription->delivery_attempts + 1,
            ]);

        if ($claimed !== 1 || $subscription->product === null) {
            return;
        }

        try {
            $product = $subscription->product;
            $url = URL::route('storefront.product', ['slug' => $product->slug]);
            $notifiable = new AnonymousNotifiable;
            $notifiable->route('mail', $subscription->email);
            Notification::send($notifiable, new CataloguedMailNotification('back_in_stock', [
                'name' => $product->name,
                'action_url' => $url,
                'action_label' => __('notifications.back_in_stock.action'),
            ]));

            $subscription->forceFill([
                'notified_at' => now(),
                'delivery_claimed_at' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $subscription->forceFill(['delivery_claimed_at' => null])->save();
            throw $exception;
        }
    }
}
