<?php

declare(strict_types=1);

namespace App\Agovena\Operations;

use App\Agovena\Extensions\ExtensionConfigurationStatus;
use App\Agovena\Extensions\ExtensionManager;
use App\Agovena\Payments\HealthResult;
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
                report($exception);
                $result = HealthResult::fail(__('admin.updates.provider_health_error'));
            }

            $row['checked'] = true;
            $row['ok'] = $result->ok;
            $row['message'] = $this->sanitize($result->message);
            $rows[] = $row;
        }

        return $rows;
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/\b(sk|pk|rk|whsec)_[A-Za-z0-9]+/i', '[redacted]', $message) ?? $message;
        $message = preg_replace('/\b(test|live)_[A-Za-z0-9]{10,}\b/', '[redacted]', $message) ?? $message;
        $message = preg_replace('/\bBearer\s+[A-Za-z0-9._\-]+\b/i', 'Bearer [redacted]', $message) ?? $message;

        return $message;
    }
}
