<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerPropertyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Merchant-defined extra customer field. Never replaces core identity or addresses.
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string|null $description
 * @property CustomerPropertyType $type
 * @property bool $is_required
 * @property array<string, mixed>|null $constraints
 * @property list<array{value: string, label: string}>|null $options
 * @property int $sort
 * @property bool $is_active
 * @property bool $show_on_registration
 * @property bool $show_on_checkout
 * @property bool $show_on_account
 * @property bool $show_on_invoice
 * @property bool $customer_editable
 * @property bool $staff_editable
 * @property bool $internal_only
 */
#[Fillable([
    'key',
    'label',
    'description',
    'type',
    'is_required',
    'constraints',
    'options',
    'sort',
    'is_active',
    'show_on_registration',
    'show_on_checkout',
    'show_on_account',
    'show_on_invoice',
    'customer_editable',
    'staff_editable',
    'internal_only',
])]
class CustomerPropertyDefinition extends Model
{
    protected function casts(): array
    {
        return [
            'type' => CustomerPropertyType::class,
            'is_required' => 'boolean',
            'constraints' => 'array',
            'options' => 'array',
            'sort' => 'integer',
            'is_active' => 'boolean',
            'show_on_registration' => 'boolean',
            'show_on_checkout' => 'boolean',
            'show_on_account' => 'boolean',
            'show_on_invoice' => 'boolean',
            'customer_editable' => 'boolean',
            'staff_editable' => 'boolean',
            'internal_only' => 'boolean',
        ];
    }

    /** @param Builder<CustomerPropertyDefinition> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<CustomerPropertyDefinition> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<CustomerPropertyValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(CustomerPropertyValue::class, 'definition_id');
    }

    public function isVisibleToCustomers(): bool
    {
        return $this->is_active && ! $this->internal_only;
    }
}
