<?php

declare(strict_types=1);

namespace App\Agovena\Customer\Properties;

use App\Agovena\Support\CountryList;
use App\Enums\CustomerPropertyType;
use App\Models\CustomerPropertyDefinition;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CustomerPropertyValidator
{
    /**
     * Safe Laravel rules from type + allowlisted constraints. Never eval merchant code.
     *
     * @param  iterable<int, CustomerPropertyDefinition>  $definitions
     * @return array<string, list<mixed>>
     */
    public function rules(iterable $definitions, string $inputKey = 'propertyValues'): array
    {
        $rules = [];

        foreach ($definitions as $definition) {
            $rules[$inputKey.'.'.$definition->key] = $this->rulesFor($definition);
        }

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    public function rulesFor(CustomerPropertyDefinition $definition): array
    {
        $required = $definition->is_required ? 'required' : 'nullable';
        $constraints = is_array($definition->constraints) ? $definition->constraints : [];
        $maxLength = $this->positiveInt($constraints['max_length'] ?? null) ?? 255;
        $minLength = $this->positiveInt($constraints['min_length'] ?? null);

        return match ($definition->type) {
            CustomerPropertyType::Text => $this->stringRules($required, $minLength, $maxLength),
            CustomerPropertyType::Textarea => $this->stringRules($required, $minLength, min(5000, max($maxLength, 1))),
            CustomerPropertyType::Email => [$required, 'email', 'max:255'],
            CustomerPropertyType::Phone => [$required, 'string', 'max:32', 'regex:/^[0-9+().\\s-]{3,32}$/'],
            CustomerPropertyType::Number => $this->numberRules($required, $constraints),
            CustomerPropertyType::Select => $this->choiceRules($required, $definition),
            CustomerPropertyType::Country => [$required, 'string', 'size:2', 'alpha', Rule::in(CountryList::codes())],
            CustomerPropertyType::Checkbox => $definition->is_required ? ['accepted'] : ['boolean'],
            CustomerPropertyType::Date => [$required, 'date', 'date_format:Y-m-d'],
        };
    }

    public function assertKey(string $key): void
    {
        if (! preg_match('/^[a-z][a-z0-9_]{0,62}$/', $key)) {
            throw ValidationException::withMessages([
                'key' => __('admin.customer_properties.invalid_key'),
            ]);
        }

        if (ReservedCustomerPropertyKeys::contains($key)) {
            throw ValidationException::withMessages([
                'key' => __('admin.customer_properties.reserved_key'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $constraints
     * @return array<string, int>
     */
    public function sanitizeConstraints(array $constraints): array
    {
        $clean = [];
        foreach (['min_length', 'max_length', 'min', 'max'] as $name) {
            $value = $this->positiveInt($constraints[$name] ?? null);
            if ($value !== null) {
                $clean[$name] = $value;
            }
        }

        return $clean;
    }

    /**
     * @param  list<mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    public function sanitizeOptions(array $options): array
    {
        $clean = [];
        foreach ($options as $option) {
            if (! is_array($option)) {
                continue;
            }
            $value = strtolower(trim((string) ($option['value'] ?? '')));
            $label = trim((string) ($option['label'] ?? ''));
            if ($value === '' || $label === '') {
                continue;
            }
            if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $value)) {
                continue;
            }
            $clean[] = ['value' => $value, 'label' => mb_substr($label, 0, 255)];
        }

        return $clean;
    }

    /**
     * @return list<mixed>
     */
    private function stringRules(string $required, ?int $minLength, int $maxLength): array
    {
        $rules = [$required, 'string', 'max:'.$maxLength];
        if ($minLength !== null) {
            $rules[] = 'min:'.$minLength;
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $constraints
     * @return list<mixed>
     */
    private function numberRules(string $required, array $constraints): array
    {
        $rules = [$required, 'numeric'];
        $min = $this->intOrNull($constraints['min'] ?? null);
        $max = $this->intOrNull($constraints['max'] ?? null);
        if ($min !== null) {
            $rules[] = 'min:'.$min;
        }
        if ($max !== null) {
            $rules[] = 'max:'.$max;
        }

        return $rules;
    }

    /**
     * @return list<mixed>
     */
    private function choiceRules(string $required, CustomerPropertyDefinition $definition): array
    {
        $values = [];
        foreach ($definition->options ?? [] as $option) {
            $values[] = (string) $option['value'];
        }

        return [$required, 'string', Rule::in($values)];
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
