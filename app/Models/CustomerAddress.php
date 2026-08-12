<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string|null $label
 * @property string $name
 * @property string|null $company
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $region
 * @property string $postal_code
 * @property string $country
 * @property string|null $phone
 * @property bool $is_default_billing
 * @property bool $is_default_shipping
 */
#[Fillable([
    'customer_id',
    'label',
    'name',
    'company',
    'line1',
    'line2',
    'city',
    'region',
    'postal_code',
    'country',
    'phone',
    'is_default_billing',
    'is_default_shipping',
])]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_default_billing' => 'boolean',
            'is_default_shipping' => 'boolean',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
