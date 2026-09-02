<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
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
 * @property int $tax_amount
 * @property int $payment_fee_amount
 * @property array<string, int|string|bool>|null $payment_fee_snapshot
 * @property int $credit_amount
 * @property string $currency
 * @property CarbonInterface|null $due_at
 * @property string|null $idempotency_key
 * @property string|null $idempotency_owner_hash
 * @property string|null $storefront_token
 * @property-read Collection<int, OrderItem> $items
 * @property-read Invoice|null $invoice
 * @property-read Collection<int, Invoice> $invoices
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
    'shipping_carrier_id',
    'shipping_service_code',
    'discount_amount',
    'tax_amount',
    'payment_fee_amount',
    'payment_fee_snapshot',
    'credit_amount',
    'discount_code',
    'referral_code',
    'tax_rate_name',
    'tax_rate_bps',
    'total_amount',
    'currency',
    'due_at',
    'idempotency_key',
    'idempotency_owner_hash',
    'custom_properties_snapshot',
])]
#[Hidden(['storefront_token'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(static function (Order $order): void {
            if (! filled($order->storefront_token)) {
                $order->storefront_token = static::generateStorefrontToken();
            }
        });
    }

    public static function generateStorefrontToken(): string
    {
        do {
            $token = bin2hex(random_bytes(32));
        } while (static::query()->where('storefront_token', $token)->exists());

        return $token;
    }

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal_amount' => 'integer',
            'shipping_amount' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'payment_fee_amount' => 'integer',
            'payment_fee_snapshot' => 'array',
            'credit_amount' => 'integer',
            'tax_rate_bps' => 'integer',
            'custom_properties_snapshot' => 'array',
            'total_amount' => 'integer',
            'shipping_same_as_billing' => 'boolean',
            'due_at' => 'datetime',
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

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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
        return $this->hasPaymentStatus([
            PaymentStatus::Pending,
            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
            PaymentStatus::Expired,
        ]);
    }

    public function isRetryablePayment(): bool
    {
        return $this->hasPaymentStatus([
            PaymentStatus::Pending,
            PaymentStatus::Failed,
            PaymentStatus::Cancelled,
            PaymentStatus::Expired,
        ]);
    }

    /** @param list<PaymentStatus> $statuses */
    private function hasPaymentStatus(array $statuses): bool
    {
        if ($this->status !== OrderStatus::Pending) {
            return false;
        }

        $invoices = $this->relationLoaded('invoices')
            ? $this->invoices
            : $this->invoices()->get();
        if ($invoices->contains(static fn (Invoice $invoice): bool => $invoice->status === InvoiceStatus::Void)) {
            return false;
        }

        return in_array($this->payment?->status, $statuses, true);
    }

    public function canCancelUnpaid(): bool
    {
        return $this->isAwaitingPayment();
    }
}
