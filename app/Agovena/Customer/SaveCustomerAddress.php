<?php

declare(strict_types=1);

namespace App\Agovena\Customer;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Support\Facades\DB;

final class SaveCustomerAddress
{
    /**
     * @param  array{label?: string|null, is_default_billing?: bool, is_default_shipping?: bool}  $meta
     */
    public function handle(Customer $customer, AddressData $address, array $meta = [], ?CustomerAddress $existing = null): CustomerAddress
    {
        return DB::transaction(function () use ($customer, $address, $meta, $existing): CustomerAddress {
            $model = $existing ?? new CustomerAddress(['customer_id' => $customer->id]);

            if ($existing !== null && (int) $existing->customer_id !== (int) $customer->id) {
                abort(404);
            }

            $model->fill([
                ...$address->toCustomerAddressAttributes(),
                'label' => isset($meta['label']) ? (trim((string) $meta['label']) ?: null) : $model->label,
                'is_default_billing' => (bool) ($meta['is_default_billing'] ?? $model->is_default_billing),
                'is_default_shipping' => (bool) ($meta['is_default_shipping'] ?? $model->is_default_shipping),
            ]);
            $model->customer_id = $customer->id;
            $model->save();

            if ($model->is_default_billing) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->whereKeyNot($model->id)
                    ->update(['is_default_billing' => false]);
            }

            if ($model->is_default_shipping) {
                CustomerAddress::query()
                    ->where('customer_id', $customer->id)
                    ->whereKeyNot($model->id)
                    ->update(['is_default_shipping' => false]);
            }

            return $model->fresh() ?? $model;
        });
    }
}
