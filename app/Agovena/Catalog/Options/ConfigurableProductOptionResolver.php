<?php

declare(strict_types=1);

namespace App\Agovena\Catalog\Options;

use App\Agovena\Provisioning\Contracts\ConfiguresProvisionedProducts;
use App\Agovena\Provisioning\Contracts\ProvidesConfigurableProductOptions;
use App\Agovena\Provisioning\ProvisionerRegistry;
use App\Agovena\Security\SensitiveDataRedactor;
use App\Models\Product;
use App\Models\ProductCapability;
use App\Models\ProductOption;
use App\Models\ProvisioningServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ConfigurableProductOptionResolver
{
    public function __construct(private readonly ProvisionerRegistry $provisioners) {}

    /** @return list<ConfigurableProductOptionDefinition> */
    public function definitions(Product $product): array
    {
        $configuration = $this->providerConfiguration($product);
        if ($configuration === null) {
            return [];
        }

        $definitions = [];
        foreach ($configuration['provider']->configurableProductOptions() as $definition) {
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9_.-]{0,63}$/D', $definition->key) !== 1
                || trim($definition->label) === ''
            ) {
                continue;
            }
            $definitions[$definition->key] ??= $definition;
        }

        return array_values($definitions);
    }

    public function definition(Product $product, string $key): ?ConfigurableProductOptionDefinition
    {
        foreach ($this->definitions($product) as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * Null means the option is not dynamically supplied by its extension.
     *
     * @param  array<string, mixed>  $selections
     * @return list<array{value: string, label: string, price_adjustment_amount: int}>|null
     */
    public function choices(Product $product, ProductOption $option, array $selections = [], bool $refresh = false): ?array
    {
        $definition = $this->definition($product, $option->key);
        if ($definition === null || ! $definition->dynamicChoices) {
            return null;
        }

        $configuration = $this->providerConfiguration($product);
        if ($configuration === null) {
            throw new ProductOptionChoicesUnavailable;
        }

        $context = $this->context($configuration, $selections);
        $cacheKey = $this->cacheKey($product, $configuration['provider_key'], $definition, $context);
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        try {
            $choices = Cache::remember($cacheKey, now()->addSeconds(30), fn (): ?array => $configuration['provider']->configurableProductOptionChoices($definition, $context)
            );
        } catch (Throwable) {
            Log::warning('Configurable product option choices could not be loaded.', [
                'provider_key' => $configuration['provider_key'],
                'option_key' => $definition->key,
            ]);

            throw new ProductOptionChoicesUnavailable;
        }

        return $this->normalizeChoices($choices ?? []);
    }

    /**
     * @param  array<string, mixed>  $selections
     */
    public function validateValue(Product $product, ProductOption $option, mixed $value, array $selections = []): ?bool
    {
        $definition = $this->definition($product, $option->key);
        if ($definition === null) {
            return null;
        }

        $configuration = $this->providerConfiguration($product);
        if ($configuration === null) {
            throw new ProductOptionChoicesUnavailable;
        }

        $context = $this->context($configuration, $selections);
        try {
            return $configuration['provider']->validateConfigurableProductOptionValue($definition, $value, $context);
        } catch (Throwable) {
            Log::warning('Configurable product option value could not be validated.', [
                'provider_key' => $configuration['provider_key'],
                'option_key' => $definition->key,
            ]);

            throw new ProductOptionChoicesUnavailable;
        }
    }

    /**
     * @param  array<string, mixed>  $selections
     */
    public function hint(Product $product, ProductOption $option, array $selections = []): ?string
    {
        $definition = $this->definition($product, $option->key);
        if ($definition === null || (! $definition->dynamicChoices && $definition->help === null)) {
            return null;
        }

        $configuration = $this->providerConfiguration($product);
        if ($configuration === null) {
            return $definition->help === null ? null : (string) __($definition->help);
        }

        $context = $this->context($configuration, $selections);
        try {
            $hint = $configuration['provider']->configurableProductOptionHint($definition, $context);
        } catch (Throwable) {
            Log::warning('Configurable product option help could not be loaded.', [
                'provider_key' => $configuration['provider_key'],
                'option_key' => $definition->key,
            ]);

            return $definition->help === null ? null : (string) __($definition->help);
        }

        $hint = is_string($hint) ? trim($hint) : '';

        return $hint !== '' ? $hint : ($definition->help === null ? null : (string) __($definition->help));
    }

    /**
     * @return array{
     *     provider: ProvidesConfigurableProductOptions,
     *     provider_key: string,
     *     config: array<string, mixed>,
     *     product_settings: array<string, mixed>
     * }|null
     */
    private function providerConfiguration(Product $product): ?array
    {
        $capability = $product->capability('provisionable');
        if (! $capability instanceof ProductCapability || $capability->hasCorruptConfig()) {
            return null;
        }

        $config = $capability->runtimeConfig();
        if (! is_array($config)) {
            return null;
        }
        $providerKey = is_string($config['provider_key'] ?? null) ? trim($config['provider_key']) : '';
        if ($providerKey === '') {
            return null;
        }

        $provider = $this->provisioners->get($providerKey);
        if (! $provider instanceof ProvidesConfigurableProductOptions) {
            return null;
        }

        $settings = is_array($config['provider_settings'] ?? null) ? $config['provider_settings'] : [];
        if ($provider instanceof ConfiguresProvisionedProducts) {
            foreach ($provider->productSettings() as $setting) {
                if ($setting->secret) {
                    unset($settings[$setting->key]);
                }
            }
        }

        return [
            'provider' => $provider,
            'provider_key' => $providerKey,
            'config' => $config,
            'product_settings' => $settings,
        ];
    }

    /**
     * @param  array{provider: ProvidesConfigurableProductOptions, provider_key: string, config: array<string, mixed>, product_settings: array<string, mixed>}  $configuration
     * @param  array<string, mixed>  $selections
     */
    private function context(array $configuration, array $selections): ConfigurableProductOptionContext
    {
        $serverSettings = null;
        $serverId = $configuration['config']['server_id'] ?? null;

        if ($serverId !== null && $serverId !== '') {
            if (! Schema::hasTable('provisioning_servers') || ! is_numeric($serverId)) {
                throw new ProductOptionChoicesUnavailable;
            }

            $server = ProvisioningServer::query()
                ->whereKey((int) $serverId)
                ->where('provider_key', $configuration['provider_key'])
                ->where('is_active', true)
                ->first();

            if ($server === null) {
                throw new ProductOptionChoicesUnavailable;
            }

            $serverSettings = $server->settings;
        }

        return new ConfigurableProductOptionContext(
            productSettings: $configuration['product_settings'],
            serverSettings: $serverSettings,
            selections: $selections,
        );
    }

    /**
     * @param  array<array-key, mixed>  $choices
     * @return list<array{value: string, label: string, price_adjustment_amount: int}>
     */
    private function normalizeChoices(array $choices): array
    {
        $normalized = [];
        $seen = [];

        foreach (array_slice($choices, 0, 250) as $choice) {
            if (! is_array($choice)) {
                continue;
            }
            $value = $choice['value'] ?? null;
            $label = $choice['label'] ?? null;
            if ((! is_string($value) && ! is_int($value)) || ! is_string($label)) {
                continue;
            }

            $value = trim((string) $value);
            $label = trim($label);
            if ($value === '' || $label === '' || isset($seen[$value])) {
                continue;
            }

            $amount = filter_var($choice['price_adjustment_amount'] ?? 0, FILTER_VALIDATE_INT);
            $normalized[] = [
                'value' => $value,
                'label' => mb_substr($label, 0, 160),
                'price_adjustment_amount' => $amount === false ? 0 : max(0, $amount),
            ];
            $seen[$value] = true;
        }

        return $normalized;
    }

    private function cacheKey(
        Product $product,
        string $providerKey,
        ConfigurableProductOptionDefinition $definition,
        ConfigurableProductOptionContext $context,
    ): string {
        $fingerprint = [
            'product_id' => $product->id,
            'provider_key' => $providerKey,
            'field' => $definition->key,
            'product_settings' => $context->productSettings,
            'server_settings' => SensitiveDataRedactor::redact($context->serverSettings ?? []),
            'selections' => SensitiveDataRedactor::redact($context->selections),
        ];

        return 'agovena.product-option-choices.'.hash('sha256', json_encode($fingerprint, JSON_THROW_ON_ERROR));
    }
}
