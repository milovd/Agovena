<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Products;

use App\Agovena\Customer\Properties\CustomerPropertyValidator;
use App\Enums\ProductOptionType;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class OptionsEditor extends Component
{
    use AuthorizesRequests;

    public int $productId;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $key = '';

    public string $label = '';

    public string $type = 'select';

    public bool $is_required = false;

    public bool $is_active = true;

    public int $sort = 0;

    public int $price_adjustment_amount = 0;

    public ?int $max_length = 255;

    public ?int $min = null;

    public ?int $max = null;

    public string $choicesText = '';

    public function mount(int $productId): void
    {
        $this->authorize('products.update');
        $this->productId = $productId;
    }

    public function create(): void
    {
        $this->authorize('products.update');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('products.update');
        $option = ProductOption::query()
            ->where('product_id', $this->productId)
            ->with('choices')
            ->findOrFail($id);

        $this->editingId = $option->id;
        $this->key = $option->key;
        $this->label = $option->label;
        $this->type = $option->type->value;
        $this->is_required = $option->is_required;
        $this->is_active = $option->is_active;
        $this->sort = $option->sort;
        $this->price_adjustment_amount = $option->price_adjustment_amount;
        $constraints = $option->constraints ?? [];
        $this->max_length = isset($constraints['max_length']) ? (int) $constraints['max_length'] : 255;
        $this->min = isset($constraints['min']) ? (int) $constraints['min'] : null;
        $this->max = isset($constraints['max']) ? (int) $constraints['max'] : null;
        $this->choicesText = $this->choicesToText($option);
        $this->showForm = true;
    }

    public function save(CustomerPropertyValidator $keyValidator): void
    {
        $this->authorize('products.update');
        $this->key = strtolower(trim($this->key));
        $keyValidator->assertKey($this->key);

        $data = $this->validate([
            'key' => [
                'required',
                'string',
                'max:64',
                Rule::unique('product_options', 'key')
                    ->where('product_id', $this->productId)
                    ->ignore($this->editingId),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(ProductOptionType::values())],
            'is_required' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['integer', 'min:0', 'max:10000'],
            'price_adjustment_amount' => ['integer', 'min:0'],
            'max_length' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'min' => ['nullable', 'integer'],
            'max' => ['nullable', 'integer'],
            'choicesText' => ['nullable', 'string', 'max:5000'],
        ]);

        $type = ProductOptionType::from($data['type']);
        $option = ProductOption::query()->updateOrCreate(
            ['id' => $this->editingId],
            [
                'product_id' => $this->productId,
                'key' => $data['key'],
                'label' => $data['label'],
                'type' => $type,
                'is_required' => $data['is_required'],
                'is_active' => $data['is_active'],
                'sort' => $data['sort'],
                'price_adjustment_amount' => $type->hasChoices() ? 0 : $data['price_adjustment_amount'],
                'constraints' => $keyValidator->sanitizeConstraints([
                    'max_length' => $data['max_length'] ?? null,
                    'min' => $data['min'] ?? null,
                    'max' => $data['max'] ?? null,
                ]),
            ],
        );

        if ($type->hasChoices()) {
            $choices = $keyValidator->sanitizeOptions($this->parseChoices($data['choicesText'] ?? ''));
            if ($choices === []) {
                $this->addError('choicesText', __('admin.product_options.choices_required'));

                return;
            }
            $option->choices()->delete();
            foreach ($choices as $index => $choice) {
                ProductOptionChoice::query()->create([
                    'product_option_id' => $option->id,
                    'value' => $choice['value'],
                    'label' => $choice['label'],
                    'price_adjustment_amount' => $this->choicePriceFromLine($data['choicesText'] ?? '', $choice['value']),
                    'sort' => $index,
                    'is_active' => true,
                ]);
            }
        } else {
            $option->choices()->delete();
        }

        session()->flash('status', __($this->editingId ? 'admin.product_options.updated' : 'admin.product_options.created'));
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $this->authorize('products.update');
        ProductOption::query()->where('product_id', $this->productId)->findOrFail($id)->delete();
        session()->flash('status', __('admin.product_options.deleted'));
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function render()
    {
        $product = Product::query()->findOrFail($this->productId);

        return view('livewire.admin.products.options-editor', [
            'options' => $product->purchaseOptions()->with('choices')->ordered()->get(),
            'types' => ProductOptionType::cases(),
        ]);
    }

    private function resetForm(): void
    {
        $this->reset(['showForm', 'editingId', 'key', 'label', 'choicesText', 'min', 'max']);
        $this->type = ProductOptionType::Select->value;
        $this->is_required = false;
        $this->is_active = true;
        $this->sort = 0;
        $this->price_adjustment_amount = 0;
        $this->max_length = 255;
        $this->resetErrorBag();
        $this->resetValidation();
    }

    private function choicesToText(ProductOption $option): string
    {
        $lines = [];
        foreach ($option->choices as $choice) {
            $line = $choice->value.':'.$choice->label;
            if ($choice->price_adjustment_amount > 0) {
                $line .= '='.$choice->price_adjustment_amount;
            }
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function parseChoices(string $text): array
    {
        $options = [];
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $pricePart = null;
            if (str_contains($line, '=')) {
                [$line, $pricePart] = array_map('trim', explode('=', $line, 2));
            }
            unset($pricePart);
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

    private function choicePriceFromLine(string $text, string $value): int
    {
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, '=')) {
                continue;
            }
            [$left, $amount] = array_map('trim', explode('=', $line, 2));
            $key = str_contains($left, ':') ? trim(explode(':', $left, 2)[0]) : $left;
            if ($key === $value && is_numeric($amount)) {
                return max(0, (int) $amount);
            }
        }

        return 0;
    }
}
