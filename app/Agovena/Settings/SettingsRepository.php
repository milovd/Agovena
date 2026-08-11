<?php

declare(strict_types=1);

namespace App\Agovena\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class SettingsRepository
{
    private const CACHE_PREFIX = 'agovena.settings.';

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $row = $this->remember($group, $key);

        if ($row === null) {
            return $default;
        }

        return $this->decode($row->value);
    }

    public function set(string $group, string $key, mixed $value): void
    {
        $encoded = $this->encode($value);

        Setting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $encoded],
        );

        Cache::forget($this->cacheKey($group, $key));
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(string $group, array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($group, (string) $key, $value);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function allInGroup(string $group): array
    {
        $rows = Setting::query()->where('group', $group)->get();
        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = $this->decode($row->value);
        }

        return $out;
    }

    private function remember(string $group, string $key): ?Setting
    {
        return Cache::remember($this->cacheKey($group, $key), 3600, function () use ($group, $key): ?Setting {
            return Setting::query()->where('group', $group)->where('key', $key)->first();
        });
    }

    private function cacheKey(string $group, string $key): string
    {
        return self::CACHE_PREFIX.$group.'.'.$key;
    }

    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_THROW_ON_ERROR);
        }

        return (string) $value;
    }

    private function decode(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value === '1' || $value === '0') {
            // Keep as string unless field cast knows boolean — callers use field type.
            return $value;
        }

        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return $value;
            }
        }

        return $value;
    }
}
