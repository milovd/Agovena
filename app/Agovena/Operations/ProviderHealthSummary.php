<?php

declare(strict_types=1);

namespace App\Agovena\Operations;

use App\Agovena\Extensions\ExtensionConfigurationStatus;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Payments\HealthResult;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProviderHealthSummary
{
    public function __construct(
        private readonly ExtensionManager $extensions,
        private readonly ExtensionConfigurationStatus $configuration,
    ) {}

    /**
     * Providers missing required settings are reported as not configured and are not probed.
     *
     * @return list<array{id: string, name: string, category: string, configured: bool, missing: list<string>, checked: bool, ok: bool, message: string}>
     */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->extensions->discover() as $manifest) {
            if (! $this->extensions->isEnabled($manifest->id)) {
                continue;
            }

            $callback = $this->extensions->context($manifest->id)?->healthCallback();
            if ($callback === null) {
                continue;
            }

            $configuration = $this->configuration->for($manifest->id);
            $row = [
                'id' => $manifest->id,
                'name' => $manifest->name,
                'category' => $manifest->category->value,
                'configured' => $configuration?->configured() ?? true,
                'missing' => $configuration->missingLabels ?? [],
                'checked' => false,
                'ok' => false,
                'message' => '',
            ];

            if (! $row['configured']) {
                $rows[] = $row;

                continue;
            }

            try {
                $result = $callback();
            } catch (Throwable $exception) {
                Log::warning('Provider health check failed.', [
                    'provider_id' => $manifest->id,
                    'exception_class' => $exception::class,
                ]);
                $result = HealthResult::fail(__('admin.updates.provider_health_error'));
            }

            $row['checked'] = true;
            $row['ok'] = $result->ok;
            $row['message'] = $result->ok ? __('admin.updates.providers_ok') : __('admin.updates.providers_fail');
            $rows[] = $row;
        }

        return $rows;
    }
}
