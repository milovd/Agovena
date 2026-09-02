<?php

declare(strict_types=1);

namespace App\Agovena\Customer\Properties;

use App\Agovena\Customer\AddressData;
use App\Agovena\Support\CountryList;
use App\Enums\CustomerPropertyType;
use App\Models\Customer;
use App\Models\CustomerPropertyDefinition;
use App\Models\CustomerPropertyValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

final class CustomerPropertyService
{
    public function __construct(
        private readonly CustomerPropertyValidator $validator,
    ) {}

    /**
     * @return Collection<int, CustomerPropertyDefinition>
     */
    public function definitionsFor(string $surface): Collection
    {
        $query = CustomerPropertyDefinition::query()->active()->ordered();

        return match ($surface) {
            'registration' => $query->where('show_on_registration', true)->where('internal_only', false)->get(),
            'checkout' => $query->where('show_on_checkout', true)->where('internal_only', false)->get(),
            'account' => $query->where('show_on_account', true)->where('internal_only', false)->get(),
            'invoice' => $query->where('show_on_invoice', true)->where('internal_only', false)->get(),
            'staff' => $query->where('staff_editable', true)->get(),
            default => collect(),
        };
    }

    /**
     * @return Collection<int, CustomerPropertyDefinition>
     */
    public function addressDefinitionsFor(string $surface): Collection
    {
        return $this->definitionsFor($surface)
            ->filter(static fn (CustomerPropertyDefinition $definition): bool => CustomerAddressProperties::isAddressKey($definition->key))
            ->values();
    }

    /**
     * @return Collection<int, CustomerPropertyDefinition>
     */
    public function nonAddressDefinitionsFor(string $surface): Collection
    {
        return $this->definitionsFor($surface)
            ->reject(static fn (CustomerPropertyDefinition $definition): bool => CustomerAddressProperties::isAddressKey($definition->key))
            ->values();
    }

    public function addressFromProperties(?Customer $customer, ?array $overlay = null): ?AddressData
    {
        if ($customer === null) {
            return null;
        }

        return CustomerAddressProperties::toAddress($customer, [
            ...$this->valuesMap($customer),
            ...($overlay ?? []),
        ]);
    }

    /** @return array<string, string|null> */
    public function addressPropertyValues(AddressData $address): array
    {
        return CustomerAddressProperties::values($address);
    }

    public function saveAddressProperties(Customer $customer, AddressData $address, string $actor): void
    {
        $definitions = CustomerPropertyDefinition::query()
            ->active()
            ->whereIn('key', CustomerAddressProperties::keys())
            ->ordered()
            ->get();

        $this->save($customer, $definitions, $this->addressPropertyValues($address), $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function valuesMap(?Customer $customer): array
    {
        if ($customer === null) {
            return [];
        }

        $values = [];
        $rows = CustomerPropertyValue::query()
            ->where('customer_id', $customer->id)
            ->with('definition')
            ->get();

        foreach ($rows as $row) {
            if ($row->definition === null) {
                continue;
            }
            $values[$row->definition->key] = $this->castStored($row->definition, $row->value);
        }

        return $values;
    }

    /**
     * @param  iterable<int, CustomerPropertyDefinition>  $definitions
     * @return array<string, mixed>
     */
    public function emptyValues(iterable $definitions, ?Customer $customer = null): array
    {
        $stored = $this->valuesMap($customer);
        $values = [];
        foreach ($definitions as $definition) {
            if (array_key_exists($definition->key, $stored)) {
                $values[$definition->key] = $stored[$definition->key];

                continue;
            }
            $values[$definition->key] = $definition->type === CustomerPropertyType::Checkbox ? false : '';
        }

        return $values;
    }

    /**
     * @param  iterable<int, CustomerPropertyDefinition>  $definitions
     * @return array<string, list<mixed>>
     */
    public function livewireRules(iterable $definitions, string $inputKey = 'propertyValues'): array
    {
        return $this->validator->rules($definitions, $inputKey);
    }

    /**
     * @param  iterable<int, CustomerPropertyDefinition>  $definitions
     * @param  array<string, mixed>  $submitted
     * @return array<string, mixed>
     */
    public function validateSubmitted(iterable $definitions, array $submitted, string $inputKey = 'propertyValues'): array
    {
        $data = [$inputKey => $submitted];
        $validated = Validator::make($data, $this->validator->rules($definitions, $inputKey))->validate();

        /** @var array<string, mixed> $values */
        $values = $validated[$inputKey] ?? [];

        return $values;
    }

    /**
     * @param  iterable<int, CustomerPropertyDefinition>  $definitions
     * @param  array<string, mixed>  $submitted
     */
    public function save(Customer $customer, iterable $definitions, array $submitted, string $actor): void
    {
        foreach ($definitions as $definition) {
            if ($actor === 'customer' && ! $definition->customer_editable) {
                continue;
            }
            if ($actor === 'staff' && ! $definition->staff_editable) {
                continue;
            }
            if (! array_key_exists($definition->key, $submitted)) {
                continue;
            }

            $raw = $this->normalizeForStorage($definition, $submitted[$definition->key] ?? null);

            CustomerPropertyValue::query()->updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'definition_id' => $definition->id,
                ],
                ['value' => $raw],
            );
        }
    }

    /**
     * @param  array<string, mixed>|null  $overlay
     * @return list<array{key: string, label: string, value: string}>
     */
    public function snapshot(?Customer $customer, ?array $overlay = null, bool $invoiceOnly = true): array
    {
        $definitions = $invoiceOnly
            ? $this->definitionsFor('invoice')
            : CustomerPropertyDefinition::query()->active()->ordered()->get();

        $stored = $this->valuesMap($customer);
        $merged = [...$stored, ...($overlay ?? [])];
        $rows = [];

        foreach ($definitions as $definition) {
            if (! array_key_exists($definition->key, $merged) && ! $definition->is_required) {
                $raw = $stored[$definition->key] ?? null;
            } else {
                $raw = $merged[$definition->key] ?? ($stored[$definition->key] ?? null);
            }

            $display = $this->displayValue($definition, $raw);
            if ($display === '') {
                continue;
            }

            $rows[] = [
                'key' => $definition->key,
                'label' => $definition->label,
                'value' => $display,
            ];
        }

        return $rows;
    }

    public function displayValue(CustomerPropertyDefinition $definition, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '';
        }

        if ($definition->type === CustomerPropertyType::Checkbox) {
            $on = $raw === true || $raw === 1 || $raw === '1' || $raw === 'true';

            return $on ? (string) __('common.yes') : (string) __('common.no');
        }

        if ($definition->type === CustomerPropertyType::Select) {
            foreach ($definition->options ?? [] as $option) {
                if ((string) $option['value'] === (string) $raw) {
                    return $option['label'];
                }
            }
        }

        if ($definition->type === CustomerPropertyType::Country) {
            $code = strtoupper((string) $raw);
            $options = CountryList::options();

            return $options[$code] ?? $code;
        }

        return trim((string) $raw);
    }

    public function wipe(Customer $customer): void
    {
        CustomerPropertyValue::query()->where('customer_id', $customer->id)->delete();
    }

    private function castStored(CustomerPropertyDefinition $definition, ?string $value): mixed
    {
        if ($definition->type === CustomerPropertyType::Checkbox) {
            return $value === '1';
        }

        return $value ?? '';
    }

    private function normalizeForStorage(CustomerPropertyDefinition $definition, mixed $value): ?string
    {
        if ($definition->type === CustomerPropertyType::Checkbox) {
            $on = $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';

            return $on ? '1' : '0';
        }

        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
