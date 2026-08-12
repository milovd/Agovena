<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_plan_change_id',
    'customer_id',
    'subscription_id',
    'from_product_id',
    'to_product_id',
    'order_id',
    'timing',
    'status',
])]
class ProductPlanChangeRequest extends Model
{
    public function planChange(): BelongsTo
    {
        return $this->belongsTo(ProductPlanChange::class, 'product_plan_change_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
