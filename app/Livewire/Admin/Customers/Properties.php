<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Admin\AdminRegistrar;
use App\Agovena\Customer\Properties\CustomerPropertyValidator;
use App\Agovena\Customer\Properties\ReservedCustomerPropertyKeys;
use App\Enums\CustomerPropertyType;
use App\Models\CustomerPropertyDefinition;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

final class Properties extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $key = '';

    public string $label = '';

    public string $type = 'text';

    public bool $is_required = false;

    public string $optionsText = '';

    public ?int $max_length = 255;

    public ?int $min_length = null;

    public ?int $min = null;

    public ?int $max = null;

    public int $sort = 0;

    public bool $is_active = true;

    public bool $show_on_registration = false;

    public bool $show_on_checkout = false;

    public bool $show_on_account = true;

    public bool $show_on_invoice = false;

    public bool $customer_editable = true;

    public bool $staff_editable = true;

    public bool $internal_only = false;

    public function mount(): void
    {
        $this->authorize('customers.manage');
    }

    public function create(): void
    {
        $this->authorize('customers.manage');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('customers.manage');
        $definition = CustomerPropertyDefinition::query()->findOrFail($id);
        $this->editingId = $definition->id;
        $this->key = $definition->key;
        $this->label = $definition->label;
        $this->type = $definition->type->value;
        $this->is_required = $definition->is_required;
        $this->sort = $definition->sort;
        $this->is_active = $definition->is_active;
        $this->show_on_registration = $definition->show_on_registration;
        $this->show_on_checkout = $definition->show_on_checkout;
        $this->show_on_account = $definition->show_on_account;
        $this->show_on_invoice = $definition->show_on_invoice;
        $this->customer_editable = $definition->customer_editable;
        $this->staff_editable = $definition->staff_editable;
        $this->internal_only = $definition->internal_only;
        $constraints = $definition->constraints ?? [];
        $this->max_length = isset($constraints['max_length']) ? (int) $constraints['max_length'] : 255;
        $this->min_length = isset($constraints['min_length']) ? (int) $constraints['min_length'] : null;
        $this->min = isset($constraints['min']) ? (int) $constraints['min'] : null;
        $this->max = isset($constraints['max']) ? (int) $constraints['max'] : null;
        $this->optionsText = $this->optionsToText($definition->options ?? []);
        $this->showForm = true;
    }

    public function save(CustomerPropertyValidator $validator): void
    {
        $this->authorize('customers.manage');
        $this->key = strtolower(trim($this->key));
        $validator->assertKey($this->key);

        $data = $this->validate([
            'key' => [
                'required',
                'string',
                'max:64',
                Rule::unique('customer_property_definitions', 'key')->ignore($this->editingId),
                Rule::notIn(ReservedCustomerPropertyKeys::KEYS),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(CustomerPropertyType::values())],
            'is_required' => ['boolean'],
            'sort' => ['integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'show_on_registration' => ['boolean'],
            'show_on_checkout' => ['boolean'],
            'show_on_account' => ['boolean'],
            'show_on_invoice' => ['boolean'],
            'customer_editable' => ['boolean'],
            'staff_editable' => ['boolean'],
            'internal_only' => ['boolean'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'min_length' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'min' => ['nullable', 'integer'],
            'max' => ['nullable', 'integer'],
            'optionsText' => ['nullable', 'string', 'max:5000'],
        ]);

        $type = CustomerPropertyType::from($data['type']);
        $options = $type->hasChoices() ? $validator->sanitizeOptions($this->parseOptionsText($data['optionsText'] ?? '')) : [];
        if ($type->hasChoices() && $options === []) {
            $this->addError('optionsText', __('admin.customer_properties.options_required'));

            return;
        }

        if ($this->internal_only) {
            $data['show_on_registration'] = false;
            $data['show_on_checkout'] = false;
            $data['show_on_account'] = false;
            $data['customer_editable'] = false;
        }

        CustomerPropertyDefinition::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'key' => $data['key'],
                'label' => $data['label'],
                'type' => $type,
                'is_required' => $data['is_required'],
                'constraints' => $validator->sanitizeConstraints([
                    'max_length' => $data['max_length'] ?? null,
                    'min_length' => $data['min_length'] ?? null,
                    'min' => $data['min'] ?? null,
                    'max' => $data['max'] ?? null,
                ]),
                'options' => $options,
                'sort' => $data['sort'],
                'is_active' => $data['is_active'],
                'show_on_registration' => $data['show_on_registration'],
                'show_on_checkout' => $data['show_on_checkout'],
                'show_on_account' => $data['show_on_account'],
                'show_on_invoice' => $data['show_on_invoice'],
                'customer_editable' => $data['customer_editable'],
                'staff_editable' => $data['staff_editable'],
                'internal_only' => $data['internal_only'],
            ],
        );

        session()->flash('status', __($this->editingId ? 'admin.customer_properties.updated' : 'admin.customer_properties.created'));
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('customers.manage');
        CustomerPropertyDefinition::query()->findOrFail($id)->delete();
        session()->flash('status', __('admin.customer_properties.deleted'));
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render(AdminRegistrar $admin)
    {
        return view('livewire.admin.customers.properties', [
            'definitions' => CustomerPropertyDefinition::query()->ordered()->paginate(25),
            'types' => CustomerPropertyType::cases(),
        ])->layout('layouts.admin', [
            'title' => __('admin.customer_properties.title'),
            'navigation' => $admin->navigationItems(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm', 'editingId', 'key', 'label', 'optionsText', 'min_length', 'min', 'max',
        ]);
        $this->type = CustomerPropertyType::Text->value;
        $this->is_required = false;
        $this->max_length = 255;
        $this->sort = 0;
        $this->is_active = true;
        $this->show_on_registration = false;
        $this->show_on_checkout = false;
        $this->show_on_account = true;
        $this->show_on_invoice = false;
        $this->customer_editable = true;
        $this->staff_editable = true;
        $this->internal_only = false;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    /**
     * @param  list<array{value?: mixed, label?: mixed}>  $options
     */
    private function optionsToText(array $options): string
    {
        $lines = [];
        foreach ($options as $option) {
            $value = trim((string) ($option['value'] ?? ''));
            $label = trim((string) ($option['label'] ?? ''));
            if ($value === '' || $label === '') {
                continue;
            }
            $lines[] = $value.':'.$label;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function parseOptionsText(string $text): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, ':')) {
                [$value, $label] = array_map('trim', explode(':', $line, 2));
            } else {
                $value = strtolower(preg_replace('/[^a-z0-9_]+/i', '_', $line) ?: '');
                $label = $line;
            }
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }
}
