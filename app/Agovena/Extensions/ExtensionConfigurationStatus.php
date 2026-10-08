<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

use Illuminate\Support\Facades\Lang;

/**
 * Answers offline whether an enabled Extension is configured: every setting its manifest
 * declares as `required` holds a value. Extensions without required settings are configured.
 * Disabled and unknown Extensions are ignored. Results are cached for the current request
 * (the service is container-scoped); no network calls and no secret values leave it.
 */
final class ExtensionConfigurationStatus
{
    /** @var array<string, ExtensionConfiguration|null> */
    private array $resolved = [];

    public function __construct(
        private readonly ExtensionManager $extensions,
        private readonly ExtensionSettingsRepository $settings,
    ) {}

    public function for(string $extensionId): ?ExtensionConfiguration
    {
        if (array_key_exists($extensionId, $this->resolved)) {
            return $this->resolved[$extensionId];
        }

        $manifest = $this->extensions->manifest($extensionId);
        if ($manifest === null || ! $this->extensions->isEnabled($extensionId)) {
            return $this->resolved[$extensionId] = null;
        }

        $required = [];
        foreach ($manifest->settings as $setting) {
            if ($setting['required'] ?? false) {
                $required[$setting['key']] = $setting['label'];
            }
        }

        $filled = $this->settings->filledKeys($extensionId, array_keys($required));
        $missingKeys = [];
        $missingLabels = [];
        foreach ($required as $key => $label) {
            if (in_array($key, $filled, true)) {
                continue;
            }

            $missingKeys[] = $key;
            $missingLabels[] = Lang::has($label) ? (string) __($label) : $label;
        }

        return $this->resolved[$extensionId] = new ExtensionConfiguration(
            $extensionId,
            $manifest->name,
            $missingKeys,
            $missingLabels,
        );
    }

    /**
     * False for disabled or unknown Extensions as well as for incomplete settings.
     */
    public function isConfigured(string $extensionId): bool
    {
        return $this->for($extensionId)?->configured() ?? false;
    }

    /**
     * The configuration of the Extension that registered this runtime provider, or null
     * when the provider does not belong to an enabled Extension (for example a Core gateway).
     */
    public function forRuntime(?object $provider): ?ExtensionConfiguration
    {
        if ($provider === null) {
            return null;
        }

        $owner = $this->extensions->ownerOf($provider);

        return $owner === null ? null : $this->for($owner);
    }

    /**
     * True unless the provider belongs to an enabled Extension that misses required settings.
     */
    public function runtimeIsConfigured(?object $provider): bool
    {
        return $this->forRuntime($provider)?->configured() ?? true;
    }

    /**
     * @return array<string, ExtensionConfiguration> enabled Extensions keyed by id
     */
    public function all(): array
    {
        $statuses = [];
        foreach ($this->extensions->discover() as $manifest) {
            $status = $this->for($manifest->id);
            if ($status !== null) {
                $statuses[$manifest->id] = $status;
            }
        }

        return $statuses;
    }

    public function flush(): void
    {
        $this->resolved = [];
    }
}
