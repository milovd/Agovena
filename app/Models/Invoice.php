<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Issued invoices snapshot commercial data. Later profile/product/tax changes must not rewrite this row.
 *
 * @property int $id
 * @property string $number
 * @property InvoiceStatus $status
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property Carbon|null $paid_at
 * @property-read Collection<int, InvoiceItem> $items
 * @property-read Order|null $order
 */
#[Fillable([
    'number',
    'status',
    'order_id',
    'customer_id',
    'customer_name',
    'customer_email',
    'billing_name',
    'billing_company',
    'billing_line1',
    'billing_line2',
    'billing_city',
    'billing_region',
    'billing_postal_code',
    'billing_country',
    'billing_phone',
    'merchant_name',
    'merchant_address',
    'issued_at',
    'due_at',
    'subtotal_amount',
    'discount_amount',
    'credit_amount',
    'tax_amount',
    'tax_rate_name',
    'tax_rate_bps',
    'total_amount',
    'currency',
    'custom_properties_snapshot',
    'paid_at',
])]
class Invoice extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'subtotal_amount' => 'integer',
            'discount_amount' => 'integer',
            'credit_amount' => 'integer',
            'tax_amount' => 'integer',
            'tax_rate_bps' => 'integer',
            'total_amount' => 'integer',
            'custom_properties_snapshot' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /** @return HasMany<InvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
