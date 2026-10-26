<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Admin;

use App\Models\Order;
use Livewire\Form;

/**
 * Inline order editing on the order page: contact details, due date and addresses. The fields
 * map one to one onto the attributes UpdateOrder accepts.
 */
final class OrderDetailsForm extends Form
{
    private const ADDRESS_FIELDS = ['name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country', 'phone'];

    public string $customerName = '';

    public string $customerEmail = '';

    public string $dueAt = '';

    /** @var array<string, string> */
    public array $billing = [];

    /** @var array<string, string> */
    public array $shipping = [];

    public bool $shippingSameAsBilling = true;

    public function fillFrom(Order $order): void
    {
        $this->customerName = (string) $order->customer_name;
        $this->customerEmail = (string) $order->customer_email;
        $this->dueAt = $order->due_at?->format('Y-m-d\TH:i') ?? '';
        foreach (self::ADDRESS_FIELDS as $field) {
            $this->billing[$field] = (string) ($order->{'billing_'.$field} ?? '');
            $this->shipping[$field] = (string) ($order->{'shipping_'.$field} ?? '');
        }
        $this->shippingSameAsBilling = (bool) $order->shipping_same_as_billing;
        $this->resetValidation();
    }

    /** @return array<string, mixed> */
    public function detailsAttributes(): array
    {
        $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'dueAt' => ['nullable', 'date'],
        ]);

        return [
            'customer_name' => trim($this->customerName),
            'customer_email' => trim($this->customerEmail),
            'due_at' => $this->dueAt !== '' ? $this->dueAt : null,
        ];
    }

    /** @return array<string, mixed> */
    public function addressAttributes(): array
    {
        $rules = ['shippingSameAsBilling' => ['boolean']];
        foreach (['billing', 'shipping'] as $type) {
            $rules[$type.'.name'] = ['nullable', 'string', 'max:255'];
            $rules[$type.'.company'] = ['nullable', 'string', 'max:255'];
            $rules[$type.'.line1'] = ['nullable', 'string', 'max:255'];
            $rules[$type.'.line2'] = ['nullable', 'string', 'max:255'];
            $rules[$type.'.city'] = ['nullable', 'string', 'max:255'];
            $rules[$type.'.region'] = ['nullable', 'string', 'max:255'];
            $rules[$type.'.postal_code'] = ['nullable', 'string', 'max:32'];
            $rules[$type.'.country'] = ['nullable', 'string', 'size:2'];
            $rules[$type.'.phone'] = ['nullable', 'string', 'max:64'];
        }
        $this->validate($rules);

        $attributes = ['shipping_same_as_billing' => $this->shippingSameAsBilling];
        foreach (['billing', 'shipping'] as $type) {
            foreach (self::ADDRESS_FIELDS as $field) {
                $value = trim((string) ($this->{$type}[$field] ?? ''));
                $value = $value === '' ? null : $value;
                $attributes[$type.'_'.$field] = $field === 'country' && $value !== null ? strtoupper($value) : $value;
            }
        }

        return $attributes;
    }
}
