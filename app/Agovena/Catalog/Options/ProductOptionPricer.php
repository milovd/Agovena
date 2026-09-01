<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Options;

use App\Agovena\Money\CurrencyConverter;
use App\Agovena\Money\Money;
use App\Agovena\Money\ResolveProductPrice;
use App\Agovena\Provisioning\Contracts\ConfiguresProvisionedProducts;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Security\OrderItemRuntimeSecretStore;
use App\Agovena\Security\SensitiveDataRedactor;
use App\Agovena\Storefront\StorefrontPreferences;
use App\Enums\ProductOptionType;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductOptionChoice;
use InvalidArgumentException;
use Throwable;

final class ProductOptionPricer
{
    public function __construct(
        private readonly ProductOptionValidator $validator,
        private readonly ResolveProductPrice $resolveProductPrice,
        private readonly CurrencyConverter $converter,
        private readonly StorefrontPreferences $preferences,
        private readonly OrderItemRuntimeSecretStore $runtimeSecrets,
        private readonly ProvisionerRegistry $provisioners,
    ) {}

    /**
     * @param  array<string, mixed>  $selections
     */
    public function unitPrice(Product $product, array $selections, ?string $currency = null): Money
    {
        $target = strtoupper($currency ?? $this->preferences->currencyCode());
        $resolved = $this->resolveProductPrice->resolve($product, $target);
        if ($resolved === null) {
            throw new InvalidArgumentException('Product is not available in currency '.$target);
        }

        $adjustmentNative = 0;
        foreach ($this->resolved($product, $selections) as $row) {
            $adjustmentNative += $row['price_adjustment_amount'];
        }

        $adjustment = $adjustmentNative === 0
            ? 0
            : $this->converter->convert($adjustmentNative, $product->currency, $target);

        return Money::of($resolved->money->amount + $adjustment, $target);
    }

    /**
     * Normalized snapshot for OrderItems and future provisioners.
     *
     * @param  array<string, mixed>  $selections
     * @return list<array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     value: mixed,
     *     display: string,
     *     price_adjustment_amount: int,
     * }>
     */
    public function snapshot(Product $product, array $selections): array
    {
        return array_map(function (array $row) use ($product): array {
            if ($this->isSensitiveOptionKey((string) $row['key'], $product)) {
                $row['value'] = '[REDACTED]';
                $row['display'] = '[REDACTED]';
            }

            $row['value'] = SensitiveDataRedactor::redact($row['value']);
            $redactedDisplay = SensitiveDataRedactor::redact($row['display']);
            $row['display'] = is_string($redactedDisplay) ? $redactedDisplay : '[REDACTED]';

            return $row;
        }, $this->resolved($product, $selections));
    }

    public function storeRuntimeSecrets(int $orderItemId, Product $product, array $selections): void
    {
        foreach ($this->resolved($product, $selections) as $row) {
            if (! $this->isSensitiveOptionKey((string) $row['key'], $product)
                && ! SensitiveDataRedactor::isSensitiveValue($row['value'])
            ) {
                continue;
            }

            $this->runtimeSecrets->put($orderItemId, (string) $row['key'], $row['value']);
        }
    }

    public function runtimeValue(array $snapshotRow, ?int $orderItemId = null): mixed
    {
        $key = (string) ($snapshotRow['key'] ?? '');
        if (! $this->isSensitiveOptionKey($key)) {
            if ($orderItemId !== null) {
                try {
                    $runtimeValue = $this->runtimeSecrets->get($orderItemId, $key);
                } catch (Throwable $exception) {
                    throw new InvalidArgumentException('Product option cannot be decrypted.', previous: $exception);
                }
                if ($runtimeValue !== null) {
                    return $runtimeValue;
                }
            }

            return $snapshotRow['value'] ?? null;
        }

        if ($orderItemId === null) {
            throw new InvalidArgumentException('Sensitive product option has no runtime owner.');
        }

        try {
            $value = $this->runtimeSecrets->get($orderItemId, $key);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException('Sensitive product option cannot be decrypted.', previous: $exception);
        }
        if ($value === null) {
            throw new InvalidArgumentException('Sensitive product option runtime value is unavailable.');
        }

        return $value;
    }

    public function runtimeValueForSelection(Product $product, string $key, array $selections): mixed
    {
        foreach ($this->resolved($product, $selections) as $row) {
            if ($row['key'] === $key) {
                return $row['value'];
            }
        }

        throw new InvalidArgumentException('Product option selection is not valid.');
    }

    /**
     * @param  array<string, mixed>  $selections
     * @return list<array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     value: mixed,
     *     display: string,
     *     price_adjustment_amount: int
     * }>
     */
    private function resolved(Product $product, array $selections): array
    {
        $rows = [];
        $normalized = CartLineKey::normalize($selections);

        foreach ($this->validator->activeOptions($product) as $option) {
            $submitted = $normalized[$option->key] ?? null;
            if ($submitted === null || $submitted === false) {
                continue;
            }

            $resolved = $this->resolveOption($option, $submitted);
            if ($resolved !== null) {
                $rows[] = $resolved;
            }
        }

        return $rows;
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     type: string,
     *     value: mixed,
     *     display: string,
     *     price_adjustment_amount: int
     * }|null
     */
    private function resolveOption(ProductOption $option, mixed $submitted): ?array
    {
        if ($option->type === ProductOptionType::Toggle) {
            $on = $submitted === true || $submitted === 1 || $submitted === '1';
            if (! $on) {
                return null;
            }

            return [
                'key' => $option->key,
                'label' => $option->label,
                'type' => $option->type->value,
                'value' => true,
                'display' => (string) __('common.yes'),
                'price_adjustment_amount' => max(0, $option->price_adjustment_amount),
            ];
        }

        if ($option->type === ProductOptionType::Checkbox && is_array($submitted)) {
            $choices = [];
            $amount = 0;
            $labels = [];
            foreach ($submitted as $value) {
                $choice = $this->choice($option, (string) $value);
                if ($choice === null) {
                    continue;
                }
                $choices[] = $choice->value;
                $labels[] = $choice->label;
                $amount += max(0, $choice->price_adjustment_amount);
            }
            if ($choices === []) {
                return null;
            }

            return [
                'key' => $option->key,
                'label' => $option->label,
                'type' => $option->type->value,
                'value' => $choices,
                'display' => implode(', ', $labels),
                'price_adjustment_amount' => $amount,
            ];
        }

        if (in_array($option->type, [ProductOptionType::Select, ProductOptionType::Radio], true)) {
            $choice = $this->choice($option, (string) $submitted);
            if ($choice === null) {
                return null;
            }

            return [
                'key' => $option->key,
                'label' => $option->label,
                'type' => $option->type->value,
                'value' => $choice->value,
                'display' => $choice->label,
                'price_adjustment_amount' => max(0, $choice->price_adjustment_amount),
            ];
        }

        $display = trim((string) $submitted);
        if ($display === '') {
            return null;
        }

        return [
            'key' => $option->key,
            'label' => $option->label,
            'type' => $option->type->value,
            'value' => $display,
            'display' => $display,
            'price_adjustment_amount' => max(0, $option->price_adjustment_amount),
        ];
    }

    private function choice(ProductOption $option, string $value): ?ProductOptionChoice
    {
        $choices = $option->relationLoaded('choices') ? $option->choices : $option->choices()->get();

        return $choices->first(
            static fn (ProductOptionChoice $choice): bool => $choice->is_active && $choice->value === $value,
        );
    }

    private function isSensitiveOptionKey(string $key, ?Product $product = null): bool
    {
        $normalizedKey = strtolower(trim($key));

        if ($normalizedKey === 'environment'
            || preg_match('/(?:api[_-]?key|access[_-]?key|token|secret|password|passwd|credential|authorization|private[_-]?key|connection|string|dsn)/', $normalizedKey) === 1
        ) {
            return true;
        }

        if (! $product instanceof Product) {
            return false;
        }

        $config = $product->capability('provisionable')?->runtimeConfig() ?? [];
        $providerKey = is_string($config['provider_key'] ?? null) ? trim($config['provider_key']) : '';
        $provider = $providerKey !== '' ? $this->provisioners->get($providerKey) : null;
        if (! $provider instanceof ConfiguresProvisionedProducts) {
            return $providerKey !== '';
        }

        foreach ($provider->productSettings() as $definition) {
            if ($definition->secret && $definition->key === $key) {
                return true;
            }
        }

        return false;
    }
}
