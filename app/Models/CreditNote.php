<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Accounting correction document. Does not rewrite the related invoice.
 *
 * @property int $id
 * @property string $number
 * @property CreditNoteStatus $status
 * @property int $invoice_id
 * @property int|null $order_id
 * @property int|null $customer_id
 * @property int|null $created_by
 * @property Carbon $issued_at
 * @property-read Collection<int, CreditNoteItem> $items
 * @property-read Invoice $invoice
 */
#[Fillable([
    'number',
    'status',
    'invoice_id',
    'order_id',
    'customer_id',
    'created_by',
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
    'reason',
    'subtotal_amount',
    'tax_amount',
    'total_amount',
    'tax_rate_name',
    'tax_rate_bps',
    'currency',
])]
class CreditNote extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new RuntimeException('Numbered credit notes cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'status' => CreditNoteStatus::class,
            'issued_at' => 'date',
            'subtotal_amount' => 'integer',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'tax_rate_bps' => 'integer',
        ];
    }

    /** @return HasMany<CreditNoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
