<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Options;

use App\Enums\ProductOptionType;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ProductOptionValidator
{
    /**
     * @param  array<string, mixed>  $selections
     * @return array<string, mixed>
     */
    public function validate(Product $product, array $selections): array
    {
        $options = $this->activeOptions($product);
        $normalized = CartLineKey::normalize($selections);
        $clean = [];

        foreach ($options as $option) {
            $submitted = $normalized[$option->key] ?? ($selections[$option->key] ?? null);

            if ($this->isEmpty($submitted)) {
                if ($option->is_required) {
                    throw ValidationException::withMessages([
                        'optionSelections.'.$option->key => __('storefront.errors.product_option_required', ['option' => $option->label]),
                    ]);
                }

                continue;
            }

            $clean[$option->key] = $this->assertValue($option, $submitted);
        }

        return CartLineKey::normalize($clean);
    }

    /**
     * @return Collection<int, ProductOption>
     */
    public function activeOptions(Product $product): Collection
    {
        if ($product->relationLoaded('purchaseOptions')) {
            return $product->purchaseOptions
                ->where('is_active', true)
                ->sortBy([['sort', 'asc'], ['id', 'asc']])
                ->values();
        }

        return $product->purchaseOptions()->active()->ordered()->with(['choices' => function ($query): void {
            $query->where('is_active', true)->orderBy('sort')->orderBy('id');
        }])->get();
    }

    public function hasRequiredUnfilled(Product $product, array $selections): bool
    {
        foreach ($this->activeOptions($product) as $option) {
            if (! $option->is_required) {
                continue;
            }
            $submitted = $selections[$option->key] ?? null;
            if ($this->isEmpty($submitted)) {
                return true;
            }
        }

        return false;
    }

    private function assertValue(ProductOption $option, mixed $submitted): mixed
    {
        $constraints = is_array($option->constraints) ? $option->constraints : [];

        return match ($option->type) {
            ProductOptionType::Select, ProductOptionType::Radio => $this->assertChoice($option, $submitted),
            ProductOptionType::Checkbox => $this->assertChoices($option, $submitted),
            ProductOptionType::Toggle => $this->assertToggle($submitted),
            ProductOptionType::Text => $this->assertText($option, $submitted, $constraints),
            ProductOptionType::Number => $this->assertNumber($option, $submitted, $constraints),
        };
    }

    private function assertChoice(ProductOption $option, mixed $submitted): string
    {
        $value = trim((string) $submitted);
        $choice = $this->findChoice($option, $value);
        if ($choice === null || ! $choice->is_active) {
            throw ValidationException::withMessages([
                'optionSelections.'.$option->key => __('storefront.errors.product_option_invalid', ['option' => $option->label]),
            ]);
        }

        return $choice->value;
    }

    /**
     * @return list<string>
     */
    private function assertChoices(ProductOption $option, mixed $submitted): array
    {
        $values = is_array($submitted) ? $submitted : [$submitted];
        $clean = [];
        foreach ($values as $value) {
            $clean[] = $this->assertChoice($option, $value);
        }

        return array_values(array_unique($clean));
    }

    private function assertToggle(mixed $submitted): bool
    {
        return $submitted === true || $submitted === 1 || $submitted === '1' || $submitted === 'true' || $submitted === 'on';
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private function assertText(ProductOption $option, mixed $submitted, array $constraints): string
    {
        $value = trim((string) $submitted);
        $max = isset($constraints['max_length']) ? (int) $constraints['max_length'] : 255;
        if (mb_strlen($value) > max(1, $max)) {
            throw ValidationException::withMessages([
                'optionSelections.'.$option->key => __('storefront.errors.product_option_invalid', ['option' => $option->label]),
            ]);
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $constraints
     */
    private function assertNumber(ProductOption $option, mixed $submitted, array $constraints): string
    {
        if (! is_numeric($submitted)) {
            throw ValidationException::withMessages([
                'optionSelections.'.$option->key => __('storefront.errors.product_option_invalid', ['option' => $option->label]),
            ]);
        }
        $number = (float) $submitted;
        if (isset($constraints['min']) && $number < (float) $constraints['min']) {
            throw ValidationException::withMessages([
                'optionSelections.'.$option->key => __('storefront.errors.product_option_invalid', ['option' => $option->label]),
            ]);
        }
        if (isset($constraints['max']) && $number > (float) $constraints['max']) {
            throw ValidationException::withMessages([
                'optionSelections.'.$option->key => __('storefront.errors.product_option_invalid', ['option' => $option->label]),
            ]);
        }

        return (string) $submitted;
    }

    private function findChoice(ProductOption $option, string $value): ?ProductOptionChoice
    {
        $choices = $option->relationLoaded('choices')
            ? $option->choices
            : $option->choices()->get();

        return $choices->first(
            static fn (ProductOptionChoice $choice): bool => $choice->value === $value,
        );
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === false) {
            return true;
        }

        return is_array($value) && $value === [];
    }
}
