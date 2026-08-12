<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $currency
 * @property int $balance_amount
 */
#[Fillable(['customer_id', 'currency', 'balance_amount'])]
class CustomerCreditAccount extends Model
{
    protected function casts(): array
    {
        return ['balance_amount' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
