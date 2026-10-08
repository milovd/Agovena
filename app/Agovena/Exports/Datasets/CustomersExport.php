<?php

declare(strict_types=1);

namespace App\Agovena\Exports\Datasets;

use App\Agovena\Exports\ExportDataset;
use App\Agovena\Exports\ExportFilters;
use App\Agovena\Exports\ExportValues;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Customer profiles. Account credentials (password hash, two-factor secrets,
 * remember tokens) live on the user and are never selected.
 */
final class CustomersExport implements ExportDataset
{
    public function __construct(private readonly ExportValues $values) {}

    public function key(): string
    {
        return 'customers';
    }

    public function permission(): string
    {
        return 'customers.view';
    }

    public function recordElement(): string
    {
        return 'customer';
    }

    public function columns(): array
    {
        return [
            'id',
            'user_id',
            'name',
            'email',
            'email_verified_at',
            'anonymized_at',
            'deletion_requested_at',
            'orders_count',
            'created_at',
            'updated_at',
        ];
    }

    public function count(ExportFilters $filters): int
    {
        return $filters->apply(Customer::query())->count();
    }

    public function rows(ExportFilters $filters): iterable
    {
        /** @var Builder<Customer> $query */
        $query = $filters->apply(Customer::query())
            ->with('user:id,email_verified_at,anonymized_at,deletion_requested_at')
            ->withCount('orders');

        foreach ($query->lazyById(500, 'customers.id', 'id') as $customer) {
            /** @var Customer $customer */
            $user = $customer->user;

            yield [
                'id' => $customer->id,
                'user_id' => $customer->user_id,
                'name' => $customer->name,
                'email' => $customer->email,
                'email_verified_at' => $this->values->dateTime($user?->email_verified_at),
                'anonymized_at' => $this->values->dateTime($user?->anonymized_at),
                'deletion_requested_at' => $this->values->dateTime($user?->deletion_requested_at),
                'orders_count' => (int) $customer->getAttribute('orders_count'),
                'created_at' => $this->values->dateTime($customer->created_at),
                'updated_at' => $this->values->dateTime($customer->updated_at),
            ];
        }
    }
}
