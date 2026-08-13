<?php

declare(strict_types=1);

namespace App\Agovena\Extensions;

use App\Models\ExtensionSetting;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Crypt;

/**
 * Per-extension settings store. Secrets are encrypted at rest.
 * Environment overrides use AGOVENA_EXT_{EXTENSION}_{KEY} (hyphens become underscores).
 */
final class ExtensionSettingsRepository
{
    public function get(string $extensionId, string $key, mixed $default = null): mixed
    {
        $fromEnv = $this->envOverride($extensionId, $key);
        if ($fromEnv !== null) {
            return $fromEnv;
        }

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

    public function isConfigured(string $extensionId, string $key): bool
    {
        if ($this->envOverride($extensionId, $key) !== null) {
            return true;
        }

        return ExtensionSetting::query()
            ->where('extension_id', $extensionId)
            ->where('key', $key)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
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

    private function envOverride(string $extensionId, string $key): mixed
    {
        $name = 'AGOVENA_EXT_'.strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $extensionId.'_'.$key) ?? '');
        $value = Env::get($name);
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
