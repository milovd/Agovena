<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Orders;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Orders\DeleteOrder;
use App\Agovena\Orders\UpdateOrder;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Edit extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Order $order;

    public string $customerName = '';

    public string $customerEmail = '';

    public string $billingName = '';

    public string $billingCompany = '';

    public string $billingLine1 = '';

    public string $billingLine2 = '';

    public string $billingCity = '';

    public string $billingRegion = '';

    public string $billingPostalCode = '';

    public string $billingCountry = '';

    public string $billingPhone = '';

    public string $shippingName = '';

    public string $shippingCompany = '';

    public string $shippingLine1 = '';

    public string $shippingLine2 = '';

    public string $shippingCity = '';

    public string $shippingRegion = '';

    public string $shippingPostalCode = '';

    public string $shippingCountry = '';

    public string $shippingPhone = '';

    public bool $shippingSameAsBilling = true;

    public string $dueAt = '';

    public bool $confirmingDelete = false;

    public function mount(Order $order): void
    {
        $this->authorize('orders.update');

        $this->order = $order->load(['invoices', 'creditNotes', 'refunds']);
        $this->fillFromOrder();
    }

    public function save(UpdateOrder $update): void
    {
        $this->authorize('orders.update');

        $data = $this->validate([
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'billingName' => ['nullable', 'string', 'max:255'],
            'billingCompany' => ['nullable', 'string', 'max:255'],
            'billingLine1' => ['nullable', 'string', 'max:255'],
            'billingLine2' => ['nullable', 'string', 'max:255'],
            'billingCity' => ['nullable', 'string', 'max:255'],
            'billingRegion' => ['nullable', 'string', 'max:255'],
            'billingPostalCode' => ['nullable', 'string', 'max:32'],
            'billingCountry' => ['nullable', 'string', 'size:2'],
            'billingPhone' => ['nullable', 'string', 'max:64'],
            'shippingName' => ['nullable', 'string', 'max:255'],
            'shippingCompany' => ['nullable', 'string', 'max:255'],
            'shippingLine1' => ['nullable', 'string', 'max:255'],
            'shippingLine2' => ['nullable', 'string', 'max:255'],
            'shippingCity' => ['nullable', 'string', 'max:255'],
            'shippingRegion' => ['nullable', 'string', 'max:255'],
            'shippingPostalCode' => ['nullable', 'string', 'max:32'],
            'shippingCountry' => ['nullable', 'string', 'size:2'],
            'shippingPhone' => ['nullable', 'string', 'max:64'],
            'shippingSameAsBilling' => ['boolean'],
            'dueAt' => ['nullable', 'date'],
        ]);

        $this->order = $update->handle($this->order, [
            'customer_name' => trim($data['customerName']),
            'customer_email' => trim($data['customerEmail']),
            'billing_name' => $this->nullableTrim($data['billingName']),
            'billing_company' => $this->nullableTrim($data['billingCompany']),
            'billing_line1' => $this->nullableTrim($data['billingLine1']),
            'billing_line2' => $this->nullableTrim($data['billingLine2']),
            'billing_city' => $this->nullableTrim($data['billingCity']),
            'billing_region' => $this->nullableTrim($data['billingRegion']),
            'billing_postal_code' => $this->nullableTrim($data['billingPostalCode']),
            'billing_country' => $this->nullableUpper($data['billingCountry']),
            'billing_phone' => $this->nullableTrim($data['billingPhone']),
            'shipping_name' => $this->nullableTrim($data['shippingName']),
            'shipping_company' => $this->nullableTrim($data['shippingCompany']),
            'shipping_line1' => $this->nullableTrim($data['shippingLine1']),
            'shipping_line2' => $this->nullableTrim($data['shippingLine2']),
            'shipping_city' => $this->nullableTrim($data['shippingCity']),
            'shipping_region' => $this->nullableTrim($data['shippingRegion']),
            'shipping_postal_code' => $this->nullableTrim($data['shippingPostalCode']),
            'shipping_country' => $this->nullableUpper($data['shippingCountry']),
            'shipping_phone' => $this->nullableTrim($data['shippingPhone']),
            'shipping_same_as_billing' => (bool) $data['shippingSameAsBilling'],
            'due_at' => $data['dueAt'] !== '' ? $data['dueAt'] : null,
        ]);

        $this->fillFromOrder();
        session()->flash('status', __('admin.orders.flash.updated'));
    }

    public function confirmDelete(): void
    {
        $this->authorize('orders.delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteOrder(DeleteOrder $delete): void
    {
        $this->authorize('orders.delete');

        if (! $this->requireRecentPassword('deleteOrder')) {
            return;
        }

        $staff = Auth::user();
        if (! $staff instanceof User) {
            abort(403);
        }

        try {
            $delete->handle($this->order, $staff);
            session()->flash('status', __('admin.orders.flash.deleted'));
            $this->redirect(route('admin.orders.index'), navigate: true);
        } catch (ValidationException $exception) {
            $this->confirmingDelete = false;
            session()->flash('error', $exception->errors()['order'][0] ?? $exception->getMessage());
        }
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.orders.edit')->layout('layouts.admin', [
            'title' => __('admin.orders.edit_title', ['number' => $this->order->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function fillFromOrder(): void
    {
        $this->customerName = $this->order->customer_name;
        $this->customerEmail = $this->order->customer_email;
        foreach ([
            'billingName',
            'billingCompany',
            'billingLine1',
            'billingLine2',
            'billingCity',
            'billingRegion',
            'billingPostalCode',
            'billingCountry',
            'billingPhone',
            'shippingName',
            'shippingCompany',
            'shippingLine1',
            'shippingLine2',
            'shippingCity',
            'shippingRegion',
            'shippingPostalCode',
            'shippingCountry',
            'shippingPhone',
        ] as $property) {
            $attribute = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $property));
            $this->{$property} = (string) ($this->order->{$attribute} ?? '');
        }
        $this->shippingSameAsBilling = (bool) $this->order->shipping_same_as_billing;
        $this->dueAt = $this->order->due_at?->format('Y-m-d\\TH:i') ?? '';
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableUpper(?string $value): ?string
    {
        $value = $this->nullableTrim($value);

        return $value === null ? null : strtoupper($value);
    }
}
