<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Invoices;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Invoices\DeleteInvoice;
use App\Agovena\Invoices\UpdateInvoice;
use App\Livewire\Concerns\RequiresRecentPassword;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Edit extends Component
{
    use AuthorizesRequests;
    use RequiresRecentPassword;

    public Invoice $invoice;

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

    public string $merchantName = '';

    public string $merchantAddress = '';

    public string $dueAt = '';

    public bool $confirmingDelete = false;

    public function mount(Invoice $invoice): void
    {
        $this->authorize('invoices.update');

        $this->invoice = $invoice->load(['items', 'creditNotes', 'refunds']);
        $this->fillFromInvoice();
    }

    public function save(UpdateInvoice $update): void
    {
        $this->authorize('invoices.update');

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
            'merchantName' => ['nullable', 'string', 'max:255'],
            'merchantAddress' => ['nullable', 'string', 'max:2000'],
            'dueAt' => ['nullable', 'date'],
        ]);

        $this->invoice = $update->handle($this->invoice, [
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
            'merchant_name' => $this->nullableTrim($data['merchantName']),
            'merchant_address' => $this->nullableTrim($data['merchantAddress']),
            'due_at' => $data['dueAt'] !== '' ? $data['dueAt'] : null,
        ]);

        $this->fillFromInvoice();
        session()->flash('status', __('admin.invoices.updated'));
    }

    public function confirmDelete(): void
    {
        $this->authorize('invoices.delete');
        $this->confirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->confirmingDelete = false;
    }

    public function deleteInvoice(DeleteInvoice $delete): void
    {
        $this->authorize('invoices.delete');

        if (! $this->requireRecentPassword('deleteInvoice')) {
            return;
        }

        $staff = Auth::user();
        if (! $staff instanceof User) {
            abort(403);
        }

        try {
            $delete->handle($this->invoice, $staff);
            session()->flash('status', __('admin.invoices.deleted'));
            $this->redirect(route('admin.invoices.index'), navigate: true);
        } catch (ValidationException $exception) {
            $this->confirmingDelete = false;
            session()->flash('error', $exception->errors()['invoice'][0] ?? $exception->getMessage());
        }
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.invoices.edit')->layout('layouts.admin', [
            'title' => __('admin.invoices.edit_title', ['number' => $this->invoice->number]),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function fillFromInvoice(): void
    {
        $this->customerName = $this->invoice->customer_name;
        $this->customerEmail = $this->invoice->customer_email;
        $this->billingName = (string) ($this->invoice->billing_name ?? '');
        $this->billingCompany = (string) ($this->invoice->billing_company ?? '');
        $this->billingLine1 = (string) ($this->invoice->billing_line1 ?? '');
        $this->billingLine2 = (string) ($this->invoice->billing_line2 ?? '');
        $this->billingCity = (string) ($this->invoice->billing_city ?? '');
        $this->billingRegion = (string) ($this->invoice->billing_region ?? '');
        $this->billingPostalCode = (string) ($this->invoice->billing_postal_code ?? '');
        $this->billingCountry = (string) ($this->invoice->billing_country ?? '');
        $this->billingPhone = (string) ($this->invoice->billing_phone ?? '');
        $this->merchantName = (string) ($this->invoice->merchant_name ?? '');
        $this->merchantAddress = (string) ($this->invoice->merchant_address ?? '');
        $this->dueAt = $this->invoice->due_at?->format('Y-m-d') ?? '';
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
