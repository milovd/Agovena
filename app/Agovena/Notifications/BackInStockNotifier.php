<?php

declare(strict_types=1);

namespace App\Agovena\Notifications;

use App\Models\BackInStockSubscription;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class BackInStockNotifier
{
    /**
     * @return string One-time unsubscribe token. Only its hash is persisted.
     */
    public function subscribe(Product $product, string $email): string
    {
        $email = Str::lower(trim($email));
        Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->validate();

        $token = Str::random(64);
        $subscription = BackInStockSubscription::query()->firstOrNew([
            'product_id' => $product->id,
            'email' => $email,
        ]);
        $subscription->forceFill([
            'token_hash' => hash('sha256', $token),
            'delivery_attempts' => 0,
            'delivery_claimed_at' => null,
            'notified_at' => null,
        ])->save();

        return $token;
    }

    public function unsubscribe(string $token): bool
    {
        if ($token === '' || ! preg_match('/^[A-Za-z0-9]{64}$/', $token)) {
            return false;
        }

        return BackInStockSubscription::query()
            ->where('token_hash', hash('sha256', $token))
            ->delete() > 0;
    }

    public function dispatch(Product $product): int
    {
        $count = 0;
        BackInStockSubscription::query()
            ->where('product_id', $product->id)
            ->whereNull('notified_at')
            ->eachById(function (BackInStockSubscription $subscription) use (&$count): void {
                SendBackInStockNotification::dispatch($subscription->id);
                $count++;
            });

        return $count;
    }
}
