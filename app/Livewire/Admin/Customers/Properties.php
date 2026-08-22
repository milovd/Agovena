<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Customers;

use App\Agovena\Customer\Properties\CustomerPropertyValidator;
use App\Enums\CustomerPropertyType;
use App\Models\CustomerPropertyDefinition;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class Properties extends Component
{
    use AuthorizesRequests;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $key = '';

    public string $label = '';

    public string $description = '';

    public string $type = 'text';

    public string $validation = 'string|max:255';

    /** @var list<array{value: string, label: string}> */
    public array $options = [];

    public bool $is_required = false;

    public bool $show_on_registration = false;

    public bool $show_on_checkout = false;

    public bool $show_on_account = true;

    public bool $show_on_invoice = false;

    public bool $customer_editable = true;

    public bool $staff_editable = true;

    public bool $internal_only = false;

    public bool $is_active = true;

    public int $sort = 0;

    public function mount(): void
    {
        Gate::authorize('customers.manage');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $definition = CustomerPropertyDefinition::query()->findOrFail($id);
        $this->editingId = $definition->id;
        $this->key = $definition->key;
        $this->label = $definition->label;
        $this->description = (string) ($definition->description ?? '');
        $this->type = $definition->type->value;
        $this->validation = $this->validationDisplay($definition);
        $this->options = $definition->options ?? [];
        $this->is_required = $definition->is_required;
        $this->show_on_registration = $definition->show_on_registration;
        $this->show_on_checkout = $definition->show_on_checkout;
        $this->show_on_account = $definition->show_on_account;
        $this->show_on_invoice = $definition->show_on_invoice;
        $this->customer_editable = $definition->customer_editable;
        $this->staff_editable = $definition->staff_editable;
        $this->internal_only = $definition->internal_only;
        $this->is_active = $definition->is_active;
        $this->sort = $definition->sort;
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(CustomerPropertyValidator $validator): void
    {
        Gate::authorize('customers.manage');

        $this->validate([
            'key' => [
                'required',
                'string',
                'max:63',
                'regex:/^[a-z][a-z0-9_]{0,62}$/',
                Rule::unique('customer_property_definitions', 'key')->ignore($this->editingId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', Rule::enum(CustomerPropertyType::class)],
            'validation' => ['nullable', 'string', 'max:255'],
            'options' => ['array'],
            'options.*.value' => ['nullable', 'string', 'max:63'],
            'options.*.label' => ['nullable', 'string', 'max:255'],
            'sort' => ['integer', 'min:0', 'max:9999'],
        ]);

        $validator->assertKey($this->key);

        $type = CustomerPropertyType::from($this->type);
        $constraints = $this->parseValidationRules($this->validation, $type);
        $options = $type === CustomerPropertyType::Select
            ? $validator->sanitizeOptions($this->options)
            : [];

        if ($type === CustomerPropertyType::Select && $options === []) {
            $this->addError('options', __('admin.customer_properties.options_required'));

            return;
        }

        $payload = [
            'key' => strtolower(trim($this->key)),
            'label' => trim($this->label),
            'description' => trim($this->description) !== '' ? trim($this->description) : null,
            'type' => $type,
            'options' => $options,
            'constraints' => $constraints,
            'is_required' => $this->is_required,
            'show_on_registration' => $this->show_on_registration,
            'show_on_checkout' => $this->show_on_checkout,
            'show_on_account' => $this->show_on_account,
            'show_on_invoice' => $this->show_on_invoice,
            'customer_editable' => $this->customer_editable,
            'staff_editable' => $this->staff_editable,
            'internal_only' => $this->internal_only,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
        ];

        if ($this->editingId !== null) {
            CustomerPropertyDefinition::query()->whereKey($this->editingId)->update($payload);
            session()->flash('status', __('admin.customer_properties.updated'));
        } else {
            CustomerPropertyDefinition::query()->create($payload);
            session()->flash('status', __('admin.customer_properties.created'));
        }

        $this->resetForm();
    }

    public function delete(int $id): void
    {
        Gate::authorize('customers.manage');
        CustomerPropertyDefinition::query()->whereKey($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        session()->flash('status', __('admin.customer_properties.deleted'));
    }

    public function toggleField(int $id, string $field): void
    {
        Gate::authorize('customers.manage');

        $allowed = ['is_required', 'show_on_invoice', 'customer_editable'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $definition = CustomerPropertyDefinition::query()->findOrFail($id);
        $definition->update([
            $field => ! $definition->{$field},
        ]);
    }

    public function addOption(): void
    {
        $this->options[] = ['value' => '', 'label' => ''];
    }

    public function removeOption(int $index): void
    {
        unset($this->options[$index]);
        $this->options = array_values($this->options);
    }

    public function render(): View
    {
        return view('livewire.admin.customers.properties', [
            'definitions' => CustomerPropertyDefinition::query()->ordered()->get(),
            'types' => CustomerPropertyType::cases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingId',
            'key',
            'label',
            'description',
            'type',
            'validation',
            'options',
            'is_required',
            'show_on_registration',
            'show_on_checkout',
            'show_on_account',
            'show_on_invoice',
            'customer_editable',
            'staff_editable',
            'internal_only',
            'is_active',
            'sort',
        ]);
        $this->showForm = false;
        $this->type = 'text';
        $this->validation = 'string|max:255';
        $this->show_on_account = true;
        $this->customer_editable = true;
        $this->staff_editable = true;
        $this->is_active = true;
    }

    private function validationDisplay(CustomerPropertyDefinition $definition): string
    {
        $constraints = is_array($definition->constraints) ? $definition->constraints : [];

        return match ($definition->type) {
            CustomerPropertyType::Text, CustomerPropertyType::Textarea => $this->stringValidationDisplay($constraints),
            CustomerPropertyType::Number => $this->numberValidationDisplay($constraints),
            CustomerPropertyType::Email => 'email|max:255',
            CustomerPropertyType::Phone => 'string|max:32',
            CustomerPropertyType::Date => 'date|date_format:Y-m-d',
            CustomerPropertyType::Checkbox => 'boolean',
            CustomerPropertyType::Select, CustomerPropertyType::Country => '',
        };
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private function stringValidationDisplay(array $constraints): string
    {
        $parts = ['string'];
        if (isset($constraints['min_length'])) {
            $parts[] = 'min:'.(int) $constraints['min_length'];
        }
        $parts[] = 'max:'.(int) ($constraints['max_length'] ?? 255);

        return implode('|', $parts);
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private function numberValidationDisplay(array $constraints): string
    {
        $parts = ['numeric'];
        if (isset($constraints['min'])) {
            $parts[] = 'min:'.(int) $constraints['min'];
        }
        if (isset($constraints['max'])) {
            $parts[] = 'max:'.(int) $constraints['max'];
        }

        return implode('|', $parts);
    }

    /**
     * @return array<string, int>
     */
    private function parseValidationRules(string $validation, CustomerPropertyType $type): array
    {
        if (! in_array($type, [CustomerPropertyType::Text, CustomerPropertyType::Textarea, CustomerPropertyType::Number], true)) {
            return [];
        }

        $constraints = [];
        foreach (explode('|', $validation) as $rule) {
            $rule = trim($rule);
            if (preg_match('/^max:(\d+)$/', $rule, $matches)) {
                $constraints[$type === CustomerPropertyType::Number ? 'max' : 'max_length'] = (int) $matches[1];
            }
            if (preg_match('/^min:(\d+)$/', $rule, $matches)) {
                $constraints[$type === CustomerPropertyType::Number ? 'min' : 'min_length'] = (int) $matches[1];
            }
        }

        return app(CustomerPropertyValidator::class)->sanitizeConstraints($constraints);
    }
}
