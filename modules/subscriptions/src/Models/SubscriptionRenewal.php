<?php

declare(strict_types=1);

namespace Agovena\Modules\Subscriptions\Models;

use Agovena\Modules\Subscriptions\Enums\RenewalStatus;
use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int|null $order_id
 * @property CarbonInterface $period_start
 * @property CarbonInterface $period_end
 * @property RenewalStatus $status
 */
final class SubscriptionRenewal extends Model
{
    protected $table = 'subscription_renewals';

    protected $fillable = [
        'subscription_id',
        'order_id',
        'period_start',
        'period_end',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'status' => RenewalStatus::class,
        ];
    }

    /** @return BelongsTo<Subscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
