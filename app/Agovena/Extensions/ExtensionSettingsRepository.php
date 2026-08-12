<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

use App\Models\ExtensionSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-extension settings store. Secrets are encrypted at rest.
 */
final class ExtensionSettingsRepository
{
    public function get(string $extensionId, string $key, mixed $default = null): mixed
    {
        $row = ExtensionSetting::query()
            ->where('extension_id', $extensionId)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return $default;
        }

        if ($row->is_secret) {
            try {
                return Crypt::decryptString((string) $row->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        $decoded = json_decode((string) $row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public function set(string $extensionId, string $key, mixed $value, bool $secret = false): void
    {
        $stored = $secret
            ? Crypt::encryptString(is_string($value) ? $value : (string) json_encode($value))
            : (string) json_encode($value);

        ExtensionSetting::query()->updateOrCreate(
            ['extension_id' => $extensionId, 'key' => $key],
            ['value' => $stored, 'is_secret' => $secret],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function all(string $extensionId): array
    {
        $out = [];
        $rows = ExtensionSetting::query()->where('extension_id', $extensionId)->get();
        foreach ($rows as $row) {
            $out[$row->key] = $this->get($extensionId, $row->key);
        }

        return $out;
    }

    public function forget(string $extensionId, string $key): void
    {
        ExtensionSetting::query()
            ->where('extension_id', $extensionId)
            ->where('key', $key)
            ->delete();
    }
}
