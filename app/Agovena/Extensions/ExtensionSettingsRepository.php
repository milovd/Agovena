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
        $row = ExtensionSetting::query()
            ->where('extension_id', $extensionId)
            ->where('key', $key)
            ->first();

        if ($row === null) {
            return $this->envOverride($extensionId, $key) ?? $default;
        }
        if ($row->is_corrupt) {
            return $default;
        }

        if ($row->is_secret) {
            try {
                return Crypt::decryptString((string) $row->value);
            } catch (\Throwable) {
                $row->forceFill(['is_corrupt' => true])->save();

                return $default;
            }
        }

        $decoded = json_decode((string) $row->value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $row->value;
    }

    public function isConfigured(string $extensionId, string $key): bool
    {
        $row = ExtensionSetting::query()
            ->where('extension_id', $extensionId)
            ->where('key', $key)
            ->where('is_corrupt', false)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->first();

        return $row !== null || $this->envOverride($extensionId, $key) !== null;
    }

    public function set(string $extensionId, string $key, mixed $value, bool $secret = false): void
    {
        $stored = $secret
            ? Crypt::encryptString(is_string($value) ? $value : (string) json_encode($value))
            : (string) json_encode($value);

        ExtensionSetting::query()->updateOrCreate(
            ['extension_id' => $extensionId, 'key' => $key],
            ['value' => $stored, 'is_secret' => $secret, 'is_corrupt' => false],
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

    /** @return list<array{key: string, value: string|null, is_secret: bool}> */
    public function snapshot(string $extensionId): array
    {
        return ExtensionSetting::query()
            ->where('extension_id', $extensionId)
            ->get(['key', 'value', 'is_secret'])
            ->map(fn (ExtensionSetting $setting): array => [
                'key' => $setting->key,
                'value' => $setting->value,
                'is_secret' => $setting->is_secret,
            ])
            ->values()
            ->all();
    }

    /** @param list<mixed> $snapshot */
    public function restore(string $extensionId, array $snapshot): void
    {
        $validated = [];
        foreach ($snapshot as $setting) {
            if (! is_array($setting)) {
                throw new \RuntimeException('Extension settings snapshot is invalid.');
            }

            $key = $setting['key'] ?? null;
            $value = $setting['value'] ?? null;
            $isSecret = $setting['is_secret'] ?? null;
            if (! is_string($key)
                || $key === ''
                || ($value !== null && ! is_string($value))
                || ! is_bool($isSecret)
            ) {
                throw new \RuntimeException('Extension settings snapshot is invalid.');
            }

            $validated[] = [
                'key' => $key,
                'value' => $value,
                'is_secret' => $isSecret,
            ];
        }

        ExtensionSetting::query()->where('extension_id', $extensionId)->delete();
        foreach ($validated as $setting) {
            ExtensionSetting::query()->create([
                'extension_id' => $extensionId,
                'key' => $setting['key'],
                'value' => $setting['value'],
                'is_secret' => $setting['is_secret'],
                'is_corrupt' => false,
            ]);
        }
    }

    private function envOverride(string $extensionId, string $key): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return null;
        }

        $name = 'AGOVENA_EXT_'.strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $extensionId.'_'.$key) ?? '');
        $value = Env::get($name);
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:api[_-]?key|access[_-]?key|token|secret|password|passwd|credential|authorization|private[_-]?key|connection|string|dsn)/i', $key) === 1;
    }
}
