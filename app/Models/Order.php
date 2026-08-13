<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $number
 * @property OrderStatus $status
 * @property string $customer_name
 * @property string $customer_email
 * @property int|null $customer_id
 * @property int $subtotal_amount
 * @property int $total_amount
 * @property string $currency
 * @property string|null $idempotency_key
 * @property-read Collection<int, OrderItem> $items
 * @property-read Payment|null $payment
 */
#[Fillable([
    'number',
    'status',
    'customer_name',
    'customer_email',
    'customer_id',
    'billing_name',
    'billing_company',
    'billing_line1',
    'billing_line2',
    'billing_city',
    'billing_region',
    'billing_postal_code',
    'billing_country',
    'billing_phone',
    'shipping_name',
    'shipping_company',
    'shipping_line1',
    'shipping_line2',
    'shipping_city',
    'shipping_region',
    'shipping_postal_code',
    'shipping_country',
    'shipping_phone',
    'shipping_same_as_billing',
    'subtotal_amount',
    'shipping_amount',
    'shipping_method_label',
    'discount_amount',
    'tax_amount',
    'credit_amount',
    'discount_code',
    'tax_rate_name',
    'tax_rate_bps',
    'total_amount',
    'currency',
    'idempotency_key',
    'custom_properties_snapshot',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'credit_amount' => 'integer',
            'tax_rate_bps' => 'integer',
            'custom_properties_snapshot' => 'array',
            'total_amount' => 'integer',
            'shipping_same_as_billing' => 'boolean',
        ];
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /** @return HasMany<CreditNote, $this> */
    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    /** @return HasMany<Refund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function isAwaitingPayment(): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        if ($this->invoice?->status === InvoiceStatus::Void) {
            return false;
        }

        $status = $this->payment?->status;

        return in_array($status, [
            PaymentStatus::Pending,
            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
        ], true);
    }

    public function canCancelUnpaid(): bool
    {
        return $this->isAwaitingPayment();
    }
}
