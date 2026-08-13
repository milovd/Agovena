<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $definition_id
 * @property string|null $value
 */
#[Fillable(['customer_id', 'definition_id', 'value'])]
class CustomerPropertyValue extends Model
{
    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<CustomerPropertyDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(CustomerPropertyDefinition::class, 'definition_id');
    }
}
